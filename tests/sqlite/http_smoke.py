#!/usr/bin/env python3
"""Real loopback HTTP tests using a disposable persistent SQLite database."""
import argparse
import http.client
import os
from pathlib import Path
import re
import secrets
import signal
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
args = parser.parse_args()
passed = failed = 0


def check(name, condition):
    global passed, failed
    if condition:
        passed += 1
        print('PASS ' + name, flush=True)
    else:
        failed += 1
        print('FAIL ' + name, flush=True)


class Client:
    def __init__(self, port):
        self.port = port
        self.cookie = ''

    def request(self, path, method='GET', fields=None, raw=None, extra=None):
        headers = {'Host': f'127.0.0.1:{self.port}'}
        if self.cookie:
            headers['Cookie'] = self.cookie
        if fields is not None:
            raw = urllib.parse.urlencode(fields)
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
        raise RuntimeError('Expected form token missing; no response contents printed.')
    return match.group(1)


with tempfile.TemporaryDirectory(prefix='pathway-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)
    if init.returncode:
        raise SystemExit(1)
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    log = open(Path(directory) / 'http.log', 'wb')
    process = None

    def start():
        global process
        process = subprocess.Popen([args.php, str(ROOT / 'bin/serve.php'), str(port)],
                                   cwd=ROOT, env=env, stdout=log, stderr=log, start_new_session=True)
        for _ in range(100):
            if process.poll() is not None:
                raise RuntimeError('Local fixture server exited; no log contents printed.')
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    return
            except OSError:
                time.sleep(.1)
        raise RuntimeError('Local fixture server did not start.')

    def stop():
        if process is not None and process.poll() is None:
            os.killpg(process.pid, signal.SIGTERM)
            process.wait(timeout=10)

    try:
        start()
        client = Client(port)
        status, initial_headers, _ = client.request('/dashboard')
        check('anonymous dashboard denied', status == 401)
        status, headers, _ = client.request('/')
        check('root redirects to login', status == 303 and headers.get('location') == '/login')
        status, headers, body = client.request('/register')
        csrf = token(body)
        check('registration form available', status == 200)
        check('session cookie has HttpOnly and SameSite Strict', 'HttpOnly' in initial_headers.get('set-cookie', '') and 'SameSite=Strict' in initial_headers.get('set-cookie', ''))
        check('security headers present', "frame-ancestors 'none'" in headers.get('content-security-policy', '') and headers.get('x-content-type-options') == 'nosniff')
        check('no external assets required', not re.search(r'(?:src|href)="https?://', body))
        credentials = {'email': 'http-student@example.test', 'password': secrets.token_hex(16)}
        fields = dict(credentials, name='<script>fixture</script>', csrf_token=csrf, role='admin')
        check('registration missing CSRF rejected', client.request('/register', 'POST', credentials)[0] == 403)
        check('registration malformed name rejected', client.request('/register', 'POST', dict(credentials, **{'name[]': 'x'}, csrf_token=csrf))[0] == 422)
        check('registration weak password rejected', client.request('/register', 'POST', dict(fields, password='short'))[0] == 422)
        status, headers, _ = client.request('/register', 'POST', fields)
        check('valid registration redirects to login', status == 303 and headers.get('location') == '/login')
        with sqlite3.connect(Path(directory) / 'pathway.sqlite') as db:
            role, stored = db.execute('SELECT role, password_hash FROM users').fetchone()
        check('forged role ignored and hash persisted', role == 'student' and stored != credentials['password'] and stored.startswith('$2'))
        status, _, body = client.request('/login')
        csrf = token(body)
        check('registration flash displayed', 'Your local account is ready' in body)
        check('invalid password rejected', client.request('/login', 'POST', dict(credentials, password=secrets.token_hex(16), csrf_token=csrf))[0] == 401)
        check('SQL injection login rejected', client.request('/login', 'POST', dict(credentials, password="' OR 1=1 --", csrf_token=csrf))[0] == 401)
        check('SQL injection email rejected', client.request('/login', 'POST', dict(credentials, email="' OR 1=1 --", csrf_token=csrf))[0] == 422)
        check('invalid CSRF rejected', client.request('/login', 'POST', dict(credentials, csrf_token='0' * 64))[0] == 403)
        before = client.cookie
        status, headers, _ = client.request('/login', 'POST', dict(credentials, csrf_token=csrf))
        check('valid login redirects to dashboard', status == 303 and headers.get('location') == '/dashboard')
        check('login rotates session ID', client.cookie != before)
        status, headers, body = client.request('/dashboard')
        check('student dashboard loads', status == 200)
        check('student name escaped', '<script>fixture</script>' not in body and '&lt;script&gt;fixture&lt;/script&gt;' in body)
        check('demonstration content explicitly labeled', 'Demonstration content' in body and 'demo environment' in body)
        check('private page is not cached', headers.get('cache-control') == 'no-store')
        dashboard_csrf = token(body)
        check('logout GET rejected', client.request('/logout')[0] == 405)
        check('logout without CSRF rejected', client.request('/logout', 'POST', {})[0] == 403)
        check('old login CSRF invalid after rotation', client.request('/logout', 'POST', {'csrf_token': csrf})[0] == 403)
        old_cookie = client.cookie
        check('valid logout redirects', client.request('/logout', 'POST', {'csrf_token': dashboard_csrf})[0] == 303)
        client.cookie = old_cookie
        check('logged-out session cannot access dashboard', client.request('/dashboard')[0] == 401)
        check('POST dashboard disallowed', client.request('/dashboard', 'POST', {})[0] == 405)
        for path in ['/config.php', '/.git/config', '/.codenow/sessions/smart-code-cloud.json', '/app/bootstrap.php',
                     '/app/migrations/001_student.sql', '/var/pathway.sqlite', '/tests/sqlite/core_test.php',
                     '/Database/schema.sql', '/login.php', '/register.php', '/view_applicants.php',
                     '/resume_builder.php', '/../config.php', '/%2e%2e/config.php', '/public/index.php']:
            check('private/unmigrated route denied: ' + path, client.request(path)[0] == 404)
        check('foreign Host rejected', client.request('/login', extra={'Host': 'untrusted.example'})[0] == 421)
        check('JSON POST rejected', client.request('/login', 'POST', raw='{}', extra={'Content-Type': 'application/json'})[0] == 415)
        check('oversized POST rejected', client.request('/login', 'POST', raw='x=' + 'x' * 9000, extra={'Content-Type': 'application/x-www-form-urlencoded'})[0] == 413)
        check('local CSS served', client.request('/assets/pathway.css')[0] == 200)
        stop()
        start()
        client = Client(port)
        _, _, body = client.request('/login')
        status, _, _ = client.request('/login', 'POST', dict(credentials, csrf_token=token(body)))
        check('account login persists after server process restart', status == 303)
        check('dashboard persists after restart', client.request('/dashboard')[0] == 200)
        with sqlite3.connect(Path(directory) / 'pathway.sqlite') as db:
            db.execute("UPDATE users SET role = 'company'")
        check('database role revocation blocks active session', client.request('/dashboard')[0] == 403)
        _, _, body = client.request('/login')
        check('company login denied in student slice', client.request('/login', 'POST', dict(credentials, csrf_token=token(body)))[0] == 403)
        with sqlite3.connect(Path(directory) / 'pathway.sqlite') as db:
            check('SQLite integrity check passes', db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok')
    finally:
        stop()
        log.close()

print(f'RESULT: {passed} passed, {failed} failed')
raise SystemExit(1 if failed else 0)
