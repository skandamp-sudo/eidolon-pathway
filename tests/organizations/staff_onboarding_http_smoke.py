#!/usr/bin/env python3
import argparse
import hashlib
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

with tempfile.TemporaryDirectory(prefix='pathway-staff-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8500 + secrets.randbelow(400)
        server = subprocess.Popen(
            [args.php, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public'), str(ROOT / 'public/index.php')],
            env=env, stdout=log, stderr=subprocess.STDOUT
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
        start()

        # Direct database seeding
        db_path = Path(directory) / 'pathway.sqlite'
        conn = sqlite3.connect(str(db_path))
        conn.row_factory = sqlite3.Row
        cur = conn.cursor()
        now = int(time.time())

        # Admin user
        admin_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("AdminPass123!@#", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()

        cur.execute(
            "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
            ('Platform Admin', 'admin@eidolon-test.internal', admin_hash)
        )
        admin_id = cur.lastrowid

        # Active company
        cur.execute(
            "INSERT INTO organizations (name, slug, type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ('Cyberdyne Systems AI', 'cyberdyne-systems-ai', 'COMPANY', 'ACTIVE', now, now)
        )
        company_id = cur.lastrowid

        # Active college
        cur.execute(
            "INSERT INTO organizations (name, slug, type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ('Apex Institute of Technology', 'apex-institute-of-technology', 'COLLEGE', 'ACTIVE', now, now)
        )
        college_id = cur.lastrowid

        # Generate company staff invitation
        raw_company_token = secrets.token_hex(32)
        comp_token_hash = hashlib.sha256(raw_company_token.encode('utf-8')).hexdigest()
        cur.execute(
            """INSERT INTO organization_invitations
               (organization_id, invited_by_user_id, email, role, token_hash, expires_at, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (company_id, admin_id, 'recruiter@cyberdyne.test', 'STAFF', comp_token_hash, now + 7 * 86400, now)
        )

        # Generate college staff invitation
        raw_college_token = secrets.token_hex(32)
        coll_token_hash = hashlib.sha256(raw_college_token.encode('utf-8')).hexdigest()
        cur.execute(
            """INSERT INTO organization_invitations
               (organization_id, invited_by_user_id, email, role, token_hash, expires_at, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (college_id, admin_id, 'tpo@apex.edu.test', 'ADMIN', coll_token_hash, now + 7 * 86400, now)
        )

        conn.commit()

        # 1. Public GET /invite without token
        anon = Client(port)
        status, headers, body = anon.request('/invite')
        check('GET /invite returns 200', status == 200)
        check('GET /invite displays Accept Staff Invitation', 'Accept Staff Invitation' in body)
        check('GET /invite prompts for Invitation Token input', 'Enter Staff Invitation Token' in body)
        check('GET /invite navbar includes Accept Invite tab', 'Accept Invite' in body)

        # 2. Public GET /invite with invalid token
        status, headers, body = anon.request('/invite?token=0000000000000000000000000000000000000000000000000000000000000000')
        check('GET /invite with invalid token returns 200', status == 200)
        check('GET /invite invalid token displays problem message', 'Invitation token not found or invalid' in body)

        # 3. GET /invite with valid company token
        status, headers, body = anon.request(f'/invite?token={raw_company_token}')
        check('GET /invite valid company token returns 200', status == 200)
        check('GET /invite shows company name', 'Cyberdyne Systems AI' in body)
        check('GET /invite shows COMPANY STAFF SEAT', 'COMPANY STAFF SEAT' in body)
        check('GET /invite shows invited email', 'recruiter@cyberdyne.test' in body)
        check('GET /invite shows registration form', 'Create Your Staff Account' in body)
        csrf = token(body)
        check('GET /invite provides valid CSRF token', len(csrf) == 64)

        # 4. POST /invite without CSRF token is rejected with 403
        status, headers, body = anon.request('/invite', method='POST', fields={
            'token': raw_company_token,
            'mode': 'register',
            'name': 'Sarah Connor',
            'password': 'StrongPassword123!',
            'password_confirm': 'StrongPassword123!',
        })
        check('POST /invite without CSRF token rejected 403', status == 403)

        # 5. POST /invite with password mismatch redirects back with error
        status, headers, body = anon.request('/invite', method='POST', fields={
            'csrf_token': csrf,
            'token': raw_company_token,
            'mode': 'register',
            'name': 'Sarah Connor',
            'password': 'StrongPassword123!',
            'password_confirm': 'DifferentPassword456!',
        })
        check('POST /invite password mismatch redirects 303', status == 303)
        check('Redirects back to invite with token', f'/invite?token={raw_company_token}' in headers.get('location', ''))

        # Check flash error message
        status, headers, body = anon.request(headers['location'])
        check('Flash error displays password mismatch', 'Passwords do not match' in body)
        csrf = token(body)

        # 6. POST /invite valid registration
        status, headers, body = anon.request('/invite', method='POST', fields={
            'csrf_token': csrf,
            'token': raw_company_token,
            'mode': 'register',
            'name': 'Sarah Connor',
            'password': 'CyberdyneStaffPass123!',
            'password_confirm': 'CyberdyneStaffPass123!',
        })
        check('POST /invite valid registration redirects 303', status == 303)
        check('Redirect target is /company/dashboard', headers.get('location') == '/company/dashboard')

        # Follow redirect to /company/dashboard
        status, headers, body = anon.request('/company/dashboard')
        check('GET /company/dashboard returns 200 for newly registered staff', status == 200)
        check('Company portal displays company name', 'Cyberdyne Systems AI' in body)
        check('Company portal displays Verified Organization badge', 'Verified Organization' in body)

        # 7. Replay attack on consumed invitation
        anon2 = Client(port)
        status, headers, body = anon2.request(f'/invite?token={raw_company_token}')
        check('GET /invite with consumed token displays problem', 'This invitation has already been accepted and consumed' in body)

        # 8. College staff onboarding via /invitation/accept alias
        college_client = Client(port)
        status, headers, body = college_client.request(f'/invitation/accept?token={raw_college_token}')
        check('GET /invitation/accept returns 200', status == 200)
        check('Shows college organization name', 'Apex Institute of Technology' in body)
        check('Shows COLLEGE STAFF SEAT', 'COLLEGE STAFF SEAT' in body)
        csrf = token(body)

        status, headers, body = college_client.request('/invitation/accept', method='POST', fields={
            'csrf_token': csrf,
            'token': raw_college_token,
            'mode': 'register',
            'name': 'Dean Thomas Vance',
            'password': 'ApexCollegePass123!',
            'password_confirm': 'ApexCollegePass123!',
        })
        check('POST /invitation/accept redirects 303', status == 303)
        check('College staff redirects to /college/dashboard', headers.get('location') == '/college/dashboard')

        status, headers, body = college_client.request('/college/dashboard')
        check('GET /college/dashboard returns 200 for college staff', status == 200)
        check('College dashboard shows college name', 'Apex Institute of Technology' in body)

        # 9. Cross-role boundary enforcement
        # Company staff cannot access /college/dashboard
        status, headers, body = anon.request('/college/dashboard')
        check('Company staff cannot access /college/dashboard (403)', status == 403)

        # College staff cannot access /company/dashboard
        status, headers, body = college_client.request('/company/dashboard')
        check('College staff cannot access /company/dashboard (403)', status == 403)

        # Neither can access /admin/dashboard
        status, headers, body = anon.request('/admin/dashboard')
        check('Company staff cannot access /admin/dashboard (403)', status == 403)

        status, headers, body = college_client.request('/admin/dashboard')
        check('College staff cannot access /admin/dashboard (403)', status == 403)

        # 10. Existing account acceptance
        # Seed an existing student account
        student_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("StudentExistingPass123!", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()
        cur.execute(
            "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')",
            ('Rohan Joshi', 'rohan.joshi@cyberdyne.test', student_hash)
        )
        student_id = cur.lastrowid

        # Issue invitation to rohan.joshi@cyberdyne.test
        raw_rohan_token = secrets.token_hex(32)
        rohan_hash = hashlib.sha256(raw_rohan_token.encode('utf-8')).hexdigest()
        cur.execute(
            """INSERT INTO organization_invitations
               (organization_id, invited_by_user_id, email, role, token_hash, expires_at, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (company_id, admin_id, 'rohan.joshi@cyberdyne.test', 'REVIEWER', rohan_hash, now + 7 * 86400, now)
        )
        conn.commit()

        rohan_client = Client(port)
        status, headers, body = rohan_client.request(f'/invite?token={raw_rohan_token}')
        check('GET /invite for existing user detects account', 'EXISTING ACCOUNT DETECTED' in body)
        check('Shows reauthentication prompt', 'Accept with Existing Account' in body)
        csrf = token(body)

        # Submit wrong password
        status, headers, body = rohan_client.request('/invite', method='POST', fields={
            'csrf_token': csrf,
            'token': raw_rohan_token,
            'mode': 'accept',
            'email': 'rohan.joshi@cyberdyne.test',
            'password': 'WrongPassword123!',
        })
        check('POST /invite wrong password redirects 303', status == 303)
        status, headers, body = rohan_client.request(headers['location'])
        check('Displays credential failure flash', 'Incorrect email or password' in body)
        csrf = token(body)

        # Submit correct password
        status, headers, body = rohan_client.request('/invite', method='POST', fields={
            'csrf_token': csrf,
            'token': raw_rohan_token,
            'mode': 'accept',
            'email': 'rohan.joshi@cyberdyne.test',
            'password': 'StudentExistingPass123!',
        })
        check('POST /invite correct credentials redirects 303 to /company/dashboard', headers.get('location') == '/company/dashboard')

        status, headers, body = rohan_client.request('/company/dashboard')
        check('Upgraded existing user accesses /company/dashboard (200)', status == 200)

        # Verify user role in database was upgraded
        cur.execute("SELECT role FROM users WHERE id = ?", (student_id,))
        check('Database user role upgraded to company', cur.fetchone()['role'] == 'company')

    finally:
        stop()

print(f'\nTotal tests: {passed + failed}, Passed: {passed}, Failed: {failed}')
if failed > 0:
    exit(1)
