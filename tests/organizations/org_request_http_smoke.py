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

with tempfile.TemporaryDirectory(prefix='pathway-org-req-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8400 + secrets.randbelow(500)
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

        # Database direct seeding for admin and student
        db_path = Path(directory) / 'pathway.sqlite'
        conn = sqlite3.connect(str(db_path))
        conn.row_factory = sqlite3.Row
        cur = conn.cursor()
        now = int(time.time())

        # Generate admin password hash
        admin_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("AdminPass123!@#", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()

        cur.execute(
            "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
            ('Platform Admin', 'admin@eidolon-test.internal', admin_hash)
        )
        admin_id = cur.lastrowid
        conn.commit()

        # 1. Public /request-access Page
        anon = Client(port)
        status, headers, body = anon.request('/request-access')
        check('GET /request-access returns 200', status == 200)
        check('GET /request-access renders form with title', 'Request Organization Access' in body)
        csrf = token(body)
        check('GET /request-access provides CSRF token', len(csrf) == 64)

        # 2. Submit /request-access without CSRF rejected
        status, headers, body = anon.request('/request-access', method='POST', fields={
            'name': 'Quantum Cyber Labs',
            'type': 'COMPANY',
            'website': 'https://quantumcyber.io',
            'contact_name': 'Dr. Alan Vance',
            'contact_email': 'alan@quantumcyber.io',
            'designation': 'VP Engineering',
            'verification_notes': 'Corporate reg 99120. Domain ownership verified.',
        })
        check('POST /request-access without CSRF rejected 403', status == 403)

        # 3. Submit valid /request-access
        status, headers, body = anon.request('/request-access', method='POST', fields={
            'csrf_token': csrf,
            'name': 'Quantum Cyber Labs',
            'type': 'COMPANY',
            'website': 'https://quantumcyber.io',
            'contact_name': 'Dr. Alan Vance',
            'contact_email': 'alan@quantumcyber.io',
            'designation': 'VP Engineering',
            'verification_notes': 'Corporate reg 99120. Domain ownership verified.',
        })
        check('POST /request-access with valid data redirects 303', status == 303)
        check('POST /request-access redirects to submitted=1', headers.get('location') == '/request-access?submitted=1')

        # Follow redirect to /request-access?submitted=1
        status, headers, body = anon.request('/request-access?submitted=1')
        check('GET /request-access?submitted=1 returns 200', status == 200)
        check('Confirmation screen displays Application Received', 'Application Received' in body)

        # 4. Duplicate pending request from same email is rejected with flash error
        status, headers, body = anon.request('/request-access')
        csrf2 = token(body)
        status, headers, body = anon.request('/request-access', method='POST', fields={
            'csrf_token': csrf2,
            'name': 'Quantum Cyber Labs Dup',
            'type': 'COMPANY',
            'website': 'https://quantumcyber.io',
            'contact_name': 'Dr. Alan Vance',
            'contact_email': 'alan@quantumcyber.io',
            'designation': 'VP Engineering',
            'verification_notes': 'Corporate reg 99120 duplicate.',
        })
        check('Duplicate request redirects to /request-access', headers.get('location') == '/request-access')
        status, headers, body = anon.request('/request-access')
        check('Duplicate request shows flash error', 'already under review' in body)

        # 5. Access control: Anonymous accessing /admin or /admin/dashboard
        status, headers, body = anon.request('/admin')
        check('GET /admin redirects 303 to /admin/dashboard', status == 303 and headers.get('location') == '/admin/dashboard')

        status, headers, body = anon.request('/admin/dashboard')
        check('GET /admin/dashboard anonymous denied 401', status == 401)

        # 6. Student session accessing /admin/dashboard denied 403
        student = Client(port)
        status, headers, body = student.request('/register')
        s_csrf = token(body)
        student.request('/register', method='POST', fields={
            'csrf_token': s_csrf,
            'name': 'Sam Student',
            'email': 'sam@student.internal',
            'password': 'StudentSecret123!'
        })
        # Log in as student
        status, headers, body = student.request('/login')
        l_csrf = token(body)
        student.request('/login', method='POST', fields={
            'csrf_token': l_csrf,
            'email': 'sam@student.internal',
            'password': 'StudentSecret123!'
        })

        status, headers, body = student.request('/admin/dashboard')
        check('Student accessing /admin/dashboard denied 403', status == 403)

        # Student attempting POST to admin review endpoint denied 403
        status, headers, body = student.request('/dashboard')
        s_dash_csrf = token(body)
        status, headers, body = student.request('/admin/requests/review', method='POST', fields={
            'csrf_token': s_dash_csrf,
            'request_id': 1,
            'decision': 'APPROVE',
        })
        check('Student POST to /admin/requests/review denied 403', status == 403)

        # 7. Admin Login
        admin = Client(port)
        status, headers, body = admin.request('/admin/login')
        check('GET /admin/login returns 200', status == 200)
        a_csrf = token(body)
        status, headers, body = admin.request('/admin/login', method='POST', fields={
            'csrf_token': a_csrf,
            'email': 'admin@eidolon-test.internal',
            'password': 'AdminPass123!@#',
        })
        check('Admin login succeeds with 303 redirect', status == 303 and headers.get('location') == '/admin/dashboard')

        # 8. Admin accessing EIDOLON CONTROL tabs
        status, headers, body = admin.request('/admin/dashboard')
        check('Admin accessing /admin/dashboard returns 200', status == 200)
        check('Dashboard displays EIDOLON CONTROL Center', 'EIDOLON CONTROL Center' in body)
        check('Overview shows pending requests metric', 'Pending Access Requests' in body)

        status, headers, body = admin.request('/admin/dashboard?tab=requests')
        check('Admin tab=requests returns 200', status == 200)
        check('Tab requests shows Quantum Cyber Labs', 'Quantum Cyber Labs' in body)
        check('Tab requests shows Approve and Reject buttons', 'name="decision" value="APPROVE"' in body)
        req_csrf = token(body)

        # 9. Admin approves organization request
        status, headers, body = admin.request('/admin/requests/review', method='POST', fields={
            'csrf_token': req_csrf,
            'request_id': 1,
            'decision': 'APPROVE',
            'review_notes': 'Corporate registry CR-99120 verified.',
        })
        check('Admin review POST redirects 303 to tab=requests', status == 303 and 'tab=requests' in headers.get('location', ''))

        status, headers, body = admin.request('/admin/dashboard?tab=requests')
        check('Success flash shows setup invitation token generated', 'Setup invitation token generated:' in body)
        check('Request status shows APPROVED badge', 'APPROVED' in body)

        # 10. Check Organizations tab
        status, headers, body = admin.request('/admin/dashboard?tab=orgs')
        check('Admin tab=orgs returns 200', status == 200)
        check('Tab orgs displays approved active Quantum Cyber Labs', 'Quantum Cyber Labs' in body and 'Active' in body)

        # 11. Check Invitations tab and Issue Invitation
        status, headers, body = admin.request('/admin/dashboard?tab=invitations')
        check('Admin tab=invitations returns 200', status == 200)
        check('Tab invitations shows OWNER invitation for alan@quantumcyber.io', 'alan@quantumcyber.io' in body)
        inv_csrf = token(body)

        # Extract created organization id
        cur.execute("SELECT id FROM organizations WHERE name = 'Quantum Cyber Labs'")
        created_org_id = cur.fetchone()['id']

        # Admin issues direct invitation to organization
        status, headers, body = admin.request('/admin/invitations/create', method='POST', fields={
            'csrf_token': inv_csrf,
            'organization_id': created_org_id,
            'email': 'recruiter@quantumcyber.io',
            'role': 'STAFF',
        })
        check('Admin create invitation redirects 303 to tab=invitations', status == 303 and 'tab=invitations' in headers.get('location', ''))

        status, headers, body = admin.request('/admin/dashboard?tab=invitations')
        check('Invitations tab shows recruiter invitation', 'recruiter@quantumcyber.io' in body)
        revoke_csrf = token(body)

        # Find recruiter invitation ID
        cur.execute("SELECT id FROM organization_invitations WHERE email = 'recruiter@quantumcyber.io'")
        recruiter_inv_id = cur.fetchone()['id']

        # Admin revokes recruiter invitation
        status, headers, body = admin.request('/admin/invitations/revoke', method='POST', fields={
            'csrf_token': revoke_csrf,
            'invitation_id': recruiter_inv_id,
        })
        check('Admin revoke invitation redirects 303', status == 303)

        status, headers, body = admin.request('/admin/dashboard?tab=invitations')
        check('Revoked invitation displays REVOKED badge', 'REVOKED' in body)

        # 12. Check User Directory tab
        status, headers, body = admin.request('/admin/dashboard?tab=users')
        check('Admin tab=users returns 200', status == 200)
        check('User directory displays Sam Student and Platform Admin', 'Sam Student' in body and 'Platform Admin' in body)

        status, headers, body = admin.request('/admin/dashboard?tab=users&user_search=Sam')
        check('User directory search filters correctly', 'Sam Student' in body)

        # 13. Check Access Reviews tab
        status, headers, body = admin.request('/admin/dashboard?tab=access_reviews')
        check('Admin tab=access_reviews returns 200', status == 200)
        check('Access reviews displays Sensitive Record Access Reviews', 'Sensitive Record Access Reviews' in body)

        # 14. Check Audit Trail tab
        status, headers, body = admin.request('/admin/dashboard?tab=audit')
        check('Admin tab=audit returns 200', status == 200)
        check('Audit trail records org_request.approve action', 'org_request.approve' in body)

        # 15. Check Security Posture tab
        status, headers, body = admin.request('/admin/dashboard?tab=security')
        check('Admin tab=security returns 200', status == 200)
        check('Security posture displays Cryptographic Authentication', 'Cryptographic Authentication' in body)
        check('Security posture displays Secret Token Architecture', 'Secret Token Architecture' in body)
        check('Security posture displays Storage Isolation', 'Storage Isolation &amp; Integrity' in body)

    finally:
        stop()

print(f'\nRESULT: {passed} passed, {failed} failed')
if failed > 0:
    exit(1)
