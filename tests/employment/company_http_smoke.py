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

with tempfile.TemporaryDirectory(prefix='pathway-company-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8300 + secrets.randbelow(600)
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
        clientAnon = Client(port)

        # 1. Anonymous access to privileged endpoints denied
        check('anonymous company dashboard denied', clientAnon.request('/company/dashboard')[0] == 401)
        check('anonymous company job creation denied', clientAnon.request('/company/jobs/new')[0] == 401)
        check('anonymous company applicants denied', clientAnon.request('/company/applicants')[0] == 401)
        check('anonymous admin dashboard denied', clientAnon.request('/admin/dashboard')[0] == 401)

        # 2. Bootstrap platform admin via local CLI
        bootstrap = subprocess.run([
            args.php, str(ROOT / 'bin/bootstrap_admin.php'),
            '--email=admin@pathway.test',
            '--password=AdminPassword123!',
            '--name=Platform Administrator'
        ], env=env, capture_output=True)
        check('admin bootstrap CLI succeeds', bootstrap.returncode == 0)

        # 3. Seed Acme Technologies (PENDING) and recruiter user directly in database
        db_path = Path(directory) / 'pathway.sqlite'
        with sqlite3.connect(db_path) as db:
            now = int(time.time())
            # Bcrypt hash of 'AcmePassword123!'
            acme_hash = '$2y$12$e0NZh/v0XjZf7j7Q2z2E.ORjLhM8f3V8xL9p5Y7d6W5e4R3t2Y1u.'
            db.execute("""
                INSERT INTO users (id, name, email, password_hash, role, created_at)
                VALUES (2, 'Acme Recruiter', 'recruiter@acme.test', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'company', ?)
            """, (now,))
            db.execute("""
                INSERT INTO organizations (id, name, slug, type, status, created_by_user_id, created_at, updated_at)
                VALUES (1, 'Acme Technologies', 'acme-technologies', 'COMPANY', 'PENDING', 2, ?, ?)
            """, (now, now))
            db.execute("""
                INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
                VALUES (1, 2, 'OWNER', 'ACTIVE', ?, ?)
            """, (now, now))
            db.commit()

        # Update user with real bcrypt password hash
        hasher = subprocess.run([args.php, '-r', "echo password_hash('AcmePassword123!', PASSWORD_DEFAULT);"], capture_output=True, text=True)
        acme_hash = hasher.stdout.strip()
        with sqlite3.connect(db_path) as db:
            db.execute("UPDATE users SET password_hash = ? WHERE id = 2", (acme_hash,))
            db.commit()

        # 4. Attempt login with PENDING company -> must be denied (403)
        clientAcme = Client(port)
        status, _, body = clientAcme.request('/company/login')
        check('company login form loads', status == 200 and 'Company Sign In' in body)
        login_res, _, _ = clientAcme.request('/company/login', 'POST', {
            'email': 'recruiter@acme.test',
            'password': 'AcmePassword123!',
            'csrf_token': token(body)
        })
        check('company login denied while organization is pending (403)', login_res == 403)

        # 5. Admin logs in at /admin/login
        clientAdmin = Client(port)
        status, _, body = clientAdmin.request('/admin/login')
        check('admin login form loads', status == 200 and 'Administrator Sign In' in body)
        admin_login_res, admin_headers, _ = clientAdmin.request('/admin/login', 'POST', {
            'email': 'admin@pathway.test',
            'password': 'AdminPassword123!',
            'csrf_token': token(body)
        })
        check('admin login succeeds with 303', admin_login_res == 303 and admin_headers.get('location') == '/admin/dashboard')

        # 6. Admin views dashboard and sees pending Acme Technologies
        status, _, body = clientAdmin.request('/admin/dashboard')
        check('admin dashboard loads', status == 200 and 'System Governance Console' in body)
        check('admin dashboard lists pending Acme Technologies', 'Acme Technologies' in body and 'Approve &amp; Activate' in body)

        # 7. Admin approves Acme Technologies
        approve_res, approve_headers, _ = clientAdmin.request('/admin/organizations/approve', 'POST', {
            'organization_id': '1',
            'csrf_token': token(body)
        })
        check('admin approval redirects with 303', approve_res == 303 and approve_headers.get('location') == '/admin/dashboard')

        # Verify Acme is now listed under Active Organizations
        status, _, body = clientAdmin.request('/admin/dashboard')
        check('admin dashboard shows Acme in Active Organizations', 'Active Organizations' in body and 'acme-technologies' in body)

        # 8. Company staff logs in successfully now that organization is ACTIVE
        clientAcme = Client(port)
        status, _, body = clientAcme.request('/company/login')
        c_login_res, c_headers, _ = clientAcme.request('/company/login', 'POST', {
            'email': 'recruiter@acme.test',
            'password': 'AcmePassword123!',
            'csrf_token': token(body)
        })
        check('company login succeeds now that company is active', c_login_res == 303 and c_headers.get('location') == '/company/dashboard')

        # 9. Company dashboard loads
        dash_status, _, dash_body = clientAcme.request('/company/dashboard')
        check('company dashboard loads', dash_status == 200 and 'Company Recruitment Hub' in dash_body and 'Acme Technologies' in dash_body)

        # 10. Post new job opening
        status, _, body = clientAcme.request('/company/jobs/new')
        check('company job form loads', status == 200 and 'Post New Position' in body)
        post_job_res, post_headers, _ = clientAcme.request('/company/jobs/new', 'POST', {
            'csrf_token': token(body),
            'title': 'High Assurance Systems Intern',
            'role_category': 'backend',
            'location': 'San Francisco, CA',
            'status': 'active',
            'skills': 'PHP 8.4, SQLite, Security, C',
            'description': 'Help engineer verified evidence pipelines and secure recruitment backends.'
        })
        check('job creation redirects to dashboard', post_job_res == 303 and post_headers.get('location') == '/company/dashboard')

        # Verify job is listed on company dashboard
        _, _, dash_body = clientAcme.request('/company/dashboard')
        check('posted job appears on company dashboard', 'High Assurance Systems Intern' in dash_body)

        # 11. Student Alice registers and applies to the job
        clientAlice = Client(port)
        status, _, body = clientAlice.request('/register')
        alice_creds = {'email': 'alice-applicant@student.test', 'password': 'AlicePassword123!'}
        clientAlice.request('/register', 'POST', dict(alice_creds, name='Alice Applicant', csrf_token=token(body)))
        status, _, body = clientAlice.request('/login')
        clientAlice.request('/login', 'POST', dict(alice_creds, csrf_token=token(body)))

        # Alice views jobs catalog
        j_status, _, j_body = clientAlice.request('/jobs')
        check('student sees Acme job in jobs catalog', j_status == 200 and 'High Assurance Systems Intern' in j_body)

        # Alice applies for the position
        status, _, body = clientAlice.request('/jobs/view?id=1')
        apply_res, apply_headers, _ = clientAlice.request('/jobs/apply', 'POST', {
            'csrf_token': token(body),
            'job_id': '1',
            'notes': 'Excited to apply with my verified code evidence!'
        })
        check('student application redirects to applications list', apply_res == 303 and apply_headers.get('location') == '/applications')

        # Alice verifies application tracking
        app_status, _, app_body = clientAlice.request('/applications')
        check('Alice sees application under review', app_status == 200 and 'Under Review' in app_body and 'Acme Technologies' in app_body)

        # 12. Company reviews applicants and sees Alice
        status, _, body = clientAcme.request('/company/applicants')
        check('company sees Alice in applicants table', status == 200 and 'Alice Applicant' in body and 'alice-applicant@student.test' in body)
        check('company sees applicant note', 'Excited to apply with my verified code evidence!' in body)

        # 13. Company shortlists Alice
        status_res, _, _ = clientAcme.request('/company/applicants/status', 'POST', {
            'csrf_token': token(body),
            'application_id': '1',
            'status': 'shortlisted'
        })
        check('company status update redirects with 303', status_res == 303)

        # Verify status is shortlisted in company portal
        _, _, app_review_body = clientAcme.request('/company/applicants')
        check('candidate review displays Shortlisted status', 'Shortlisted' in app_review_body)

        # 14. Student Alice verifies updated status
        _, _, alice_apps_body = clientAlice.request('/applications')
        check('student tracking reflects Shortlisted decision', 'Shortlisted' in alice_apps_body)

        # 15. Cross-tenant isolation over HTTP: Seed Company B and attempt tampering
        with sqlite3.connect(db_path) as db:
            now = int(time.time())
            db.execute("""
                INSERT INTO users (id, name, email, password_hash, role, created_at)
                VALUES (4, 'Beta Talent', 'talent@beta.test', ?, 'company', ?)
            """, (acme_hash, now))
            db.execute("""
                INSERT INTO organizations (id, name, slug, type, status, created_by_user_id, created_at, updated_at)
                VALUES (2, 'Beta Corp', 'beta-corp', 'COMPANY', 'ACTIVE', 4, ?, ?)
            """, (now, now))
            db.execute("""
                INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
                VALUES (2, 4, 'OWNER', 'ACTIVE', ?, ?)
            """, (now, now))
            db.commit()

        clientBeta = Client(port)
        status, _, body = clientBeta.request('/company/login')
        clientBeta.request('/company/login', 'POST', {
            'email': 'talent@beta.test',
            'password': 'AcmePassword123!',
            'csrf_token': token(body)
        })

        # Beta views applicants -> must NOT see Alice
        beta_app_status, _, beta_app_body = clientBeta.request('/company/applicants')
        check('Company B sees zero applicants (tenant isolation)', beta_app_status == 200 and 'Alice Applicant' not in beta_app_body)

        # Beta attempts to tamper with Alice's application status
        beta_tamper_res, _, _ = clientBeta.request('/company/applicants/status', 'POST', {
            'csrf_token': token(beta_app_body),
            'application_id': '1',
            'status': 'rejected'
        })
        check('Company B tamper attempt redirected with error', beta_tamper_res == 303)

        # Verify Alice remained shortlisted
        with sqlite3.connect(db_path) as db:
            final_status = db.execute("SELECT status FROM applications WHERE id = 1").fetchone()[0]
        check('Alice application remained shortlisted after tamper attempt', final_status == 'shortlisted')

        # 16. Company logout
        c_logout_status, _, _ = clientAcme.request('/logout', 'POST', {'csrf_token': token(app_review_body)})
        check('company logout redirects', c_logout_status == 303)
        check('logged-out company session cannot access dashboard', clientAcme.request('/company/dashboard')[0] == 401)

    finally:
        stop()
        log.close()

print(f'RESULT: {passed} passed, {failed} failed')
raise SystemExit(1 if failed else 0)
