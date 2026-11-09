#!/usr/bin/env python3
import argparse
import http.client
import os
from pathlib import Path
import re
import secrets
import subprocess
import tempfile
import time
import urllib.parse
import sqlite3

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--php', default=str(ROOT / 'var/runtime/bin/php'))
args = parser.parse_args()

passed = 0
failed = 0

def check(name, ok):
    global passed, failed
    if ok:
        passed += 1
        print(f'PASS {name}')
    else:
        failed += 1
        print(f'FAIL {name}')

class Client:
    def __init__(self, port):
        self.port = port
        self.cookie = ''

    def request(self, path, method='GET', fields=None, raw=None, extra=None):
        headers = {'Host': f'127.0.0.1:{self.port}'}
        if self.cookie:
            headers['Cookie'] = self.cookie
        if fields is not None:
            raw = urllib.parse.urlencode(fields, doseq=True)
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        if extra:
            headers.update(extra)
        connection = http.client.HTTPConnection('127.0.0.1', self.port, timeout=10)
        connection.request(method, path, body=raw, headers=headers)
        response = connection.getresponse()
        body = response.read().decode('utf-8')
        result_headers = {k.lower(): v for k, v in response.getheaders()}
        if 'set-cookie' in result_headers:
            self.cookie = result_headers['set-cookie'].split(';')[0]
        status = response.status
        connection.close()
        return status, result_headers, body

def token(body):
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)
    if not match:
        raise RuntimeError('Expected form token missing from body.')
    return match.group(1)

print("====================================================================")
print("  CHECKPOINT 009D: SOCIAL AUTHENTICATION HTTP SMOKE TESTS")
print("====================================================================")

with tempfile.TemporaryDirectory(prefix='pathway-oauth-http-') as directory:
    os.chmod(directory, 0o700)
    base_env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    # Ensure provider env vars are clean initially
    base_env.pop('GOOGLE_CLIENT_ID', None)
    base_env.pop('GOOGLE_CLIENT_SECRET', None)
    base_env.pop('GITHUB_CLIENT_ID', None)
    base_env.pop('GITHUB_CLIENT_SECRET', None)

    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=base_env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start(custom_env):
        global server, port
        port = 8500 + secrets.randbelow(400)
        server = subprocess.Popen(
            [args.php, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public'), str(ROOT / 'public/index.php')],
            env=custom_env, stdout=log, stderr=subprocess.STDOUT
        )
        for _ in range(30):
            try:
                c = http.client.HTTPConnection('127.0.0.1', port, timeout=1)
                c.request('GET', '/login')
                res = c.getresponse()
                if res.status in (200, 303):
                    c.close()
                    return
                c.close()
            except OSError:
                time.sleep(0.1)
        raise RuntimeError('Local test server failed to start.')

    def stop():
        global server
        if server:
            server.terminate()
            server.wait(5)
            server = None

    try:
        # --- PHASE 1: UNCONFIGURED PROVIDER BEHAVIOR ---
        start(base_env)
        client = Client(port)

        # 1. Login screen renders informative note when unconfigured
        status, headers, body = client.request('/login')
        check('/login returns HTTP 200', status == 200)
        check('unconfigured notice rendered on login', 'OAuth single sign-on available: set' in body)
        check('fake login buttons NOT present', 'Continue with Google' not in body and 'Continue with GitHub' not in body)

        # 2. Accessing /auth/login/google when unconfigured fails closed and redirects to /login
        status, headers, body = client.request('/auth/login/google')
        check('unconfigured /auth/login/google redirects 303', status == 303)
        check('redirect destination is /login', headers.get('location') == '/login')

        status, headers, body = client.request('/login')
        check('flash error shown explaining missing configuration', 'Google sign-in is not configured' in body)

        # 3. Invalid callback parameters redirect to /login
        status, headers, body = client.request('/auth/callback/google')
        check('/auth/callback/google without params redirects 303', status == 303)
        check('redirect destination is /login', headers.get('location') == '/login')

        # 4. Unauthenticated POST to /auth/unlink or /auth/link returns 401 (with valid CSRF from guest session)
        status, headers, body = client.request('/login')
        guest_token = token(body)
        status, headers, body = client.request('/auth/unlink', method='POST', fields={'provider': 'google', 'csrf_token': guest_token})
        check('unauthenticated /auth/unlink returns 401', status == 401)

        status, headers, body = client.request('/auth/link', method='POST', fields={'provider': 'google', 'csrf_token': guest_token})
        check('unauthenticated /auth/link returns 401', status == 401)

        stop()

        # --- PHASE 2: CONFIGURED PROVIDER BEHAVIOR ---
        configured_env = dict(base_env)
        configured_env['GOOGLE_CLIENT_ID'] = 'google-prod-client-id-1234.apps.googleusercontent.com'
        configured_env['GOOGLE_CLIENT_SECRET'] = 'google-secret-9876'
        configured_env['GOOGLE_REDIRECT_URI'] = f'http://127.0.0.1:8080/auth/callback/google'
        configured_env['GITHUB_CLIENT_ID'] = 'github-prod-client-id-5678'
        configured_env['GITHUB_CLIENT_SECRET'] = 'github-secret-4321'
        configured_env['GITHUB_REDIRECT_URI'] = f'http://127.0.0.1:8080/auth/callback/github'

        start(configured_env)
        client = Client(port)

        # 5. Login screen renders active provider buttons when credentials configured
        status, headers, body = client.request('/login')
        check('/login with active providers returns 200', status == 200)
        check('Continue with Google button rendered', 'Continue with Google' in body)
        check('Continue with GitHub button rendered', 'Continue with GitHub' in body)
        check('unconfigured notice omitted when providers configured', 'OAuth single sign-on available: set' not in body)

        # 6. /auth/login/google redirects to Google with S256 PKCE and 256-bit state
        status, headers, body = client.request('/auth/login/google')
        check('/auth/login/google redirects 303 to provider endpoint', status == 303)
        google_auth_url = headers.get('location', '')
        check('redirect targets accounts.google.com', 'https://accounts.google.com/o/oauth2/v2/auth' in google_auth_url)
        check('auth URL contains client_id', 'google-prod-client-id-1234' in google_auth_url)
        check('auth URL contains PKCE S256 method', 'code_challenge_method=S256' in google_auth_url)
        check('auth URL contains code_challenge', 'code_challenge=' in google_auth_url)
        check('auth URL contains state', 'state=' in google_auth_url)

        # 7. /auth/login/github redirects to GitHub with PKCE and state
        status, headers, body = client.request('/auth/login/github')
        check('/auth/login/github redirects 303 to provider endpoint', status == 303)
        github_auth_url = headers.get('location', '')
        check('redirect targets github.com/login/oauth/authorize', 'https://github.com/login/oauth/authorize' in github_auth_url)
        check('auth URL contains GitHub client_id', 'github-prod-client-id-5678' in github_auth_url)
        check('auth URL contains code_challenge', 'code_challenge=' in github_auth_url)

        # 8. Tampered state / Login CSRF in callback returns graceful failure
        status, headers, body = client.request('/auth/callback/google?state=tampered_state_value_12345&code=fake_code')
        check('tampered state callback redirects 303 to /login', status == 303)
        check('redirect target is /login', headers.get('location') == '/login')

        status, headers, body = client.request('/login')
        check('flash error indicates Login CSRF mismatch or auth failure', 'Login CSRF detected' in body or 'Authentication failed' in body)

        # 9. Cancelled OAuth flow handled cleanly
        status, headers, body = client.request('/auth/callback/google?error=access_denied')
        check('cancelled OAuth redirects 303', status == 303)
        status, headers, body = client.request('/login')
        check('flash error indicates cancellation', 'Social sign-in was cancelled or denied' in body)

        # 10. Student Privacy & Settings displays Linked Authentication Identities
        # Register and log in student
        status, headers, body = client.request('/login')
        t = token(body)
        client.request('/register', method='POST', fields={
            'name': 'Student Alice',
            'email': 'alice.test@university.test',
            'password': 'StrongPassword123!',
            'csrf_token': t,
        })

        status, headers, body = client.request('/login')
        t = token(body)
        client.request('/login', method='POST', fields={
            'email': 'alice.test@university.test',
            'password': 'StrongPassword123!',
            'csrf_token': t,
        })

        status, headers, body = client.request('/privacy/settings')
        check('/privacy/settings returns 200', status == 200)
        check('Linked Authentication Identities card present', 'Linked Authentication Identities' in body)
        check('No social identities notice when none bound', 'No third-party social identities are currently bound' in body)
        check('Link Google button present', 'Link Google Account' in body)
        check('Link GitHub button present', 'Link Github Account' in body)

        # 11. Student clicks Link Google Account -> POST /auth/link
        t = token(body)
        status, headers, body = client.request('/auth/link', method='POST', fields={
            'provider': 'google',
            'csrf_token': t,
        })
        check('/auth/link redirects 303 to /auth/login/google?link=1', status == 303)
        check('redirect target is /auth/login/google?link=1', headers.get('location') == '/auth/login/google?link=1')

        # Follow link redirect to initiate flow with linking_user_id
        status, headers, body = client.request('/auth/login/google?link=1')
        check('/auth/login/google?link=1 initiates flow', status == 303)
        link_auth_url = headers.get('location', '')
        link_state_match = re.search(r'state=([a-f0-9]{64})', link_auth_url)
        check('linking flow generates valid 64-char state', link_state_match is not None)

        if link_state_match:
            link_state = link_state_match.group(1)
            # Verify in DB that linking_user_id is recorded on oauth_states row
            db_path = Path(directory) / 'pathway.sqlite'
            conn = sqlite3.connect(str(db_path))
            cur = conn.cursor()
            cur.execute('SELECT linking_user_id FROM oauth_states WHERE state = ?', (link_state,))
            row = cur.fetchone()
            check('linking_user_id recorded in oauth_states for linking flow', row is not None and row[0] is not None)
            conn.close()

    finally:
        stop()

print("====================================================================")
print(f"RESULT: {passed} passed, {failed} failed")
print("====================================================================")

if failed > 0:
    exit(1)
