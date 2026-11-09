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

with tempfile.TemporaryDirectory(prefix='pathway-college-http-') as directory:
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

        # 1. Anonymous access to college endpoints denied
        check('anonymous college dashboard denied (401)', clientAnon.request('/college/dashboard')[0] == 401)
        check('anonymous college student view denied (401)', clientAnon.request('/college/students/view?id=1')[0] == 401)

        # 2. College login form loads
        status, _, body = clientAnon.request('/college/login')
        check('college login page loads (200)', status == 200 and 'College Placement Portal' in body)

        # 3. Bootstrap platform admin via local CLI
        bootstrap = subprocess.run([
            args.php, str(ROOT / 'bin/bootstrap_admin.php'),
            '--email=admin@pathway.test',
            '--password=AdminPassword123!',
            '--name=Platform Administrator'
        ], env=env, capture_output=True)
        check('admin bootstrap CLI succeeds', bootstrap.returncode == 0)

        # 4. Seed College DSATM and officer directly with real hash
        db_path = Path(directory) / 'pathway.sqlite'
        hasher = subprocess.run([args.php, '-r', "echo password_hash('PlacementPassword123!', PASSWORD_DEFAULT);"], capture_output=True, text=True)
        officer_hash = hasher.stdout.strip()

        with sqlite3.connect(db_path) as db:
            now = int(time.time())
            # Officer user (id 2)
            db.execute("""
                INSERT INTO users (id, name, email, password_hash, role, created_at)
                VALUES (2, 'Prof. Sharma', 'placement@dsatm.test', ?, 'college', ?)
            """, (officer_hash, now))

            # College org (id 1, ACTIVE)
            db.execute("""
                INSERT INTO organizations (id, name, slug, type, status, created_by_user_id, created_at, updated_at)
                VALUES (1, 'Dayananda Sagar Academy (DSATM)', 'dayananda-sagar-academy-dsatm', 'COLLEGE', 'ACTIVE', 1, ?, ?)
            """, (now, now))

            # Officer membership
            db.execute("""
                INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at)
                VALUES (1, 2, 'ADMIN', 'ACTIVE', ?, ?)
            """, (now, now))

            # Also seed an active company with a job
            db.execute("""
                INSERT INTO organizations (id, name, slug, type, status, created_by_user_id, created_at, updated_at)
                VALUES (2, 'EIDOLON Research Labs', 'eidolon-research-labs', 'COMPANY', 'ACTIVE', 1, ?, ?)
            """, (now, now))

            db.execute("""
                INSERT INTO jobs (id, organization_id, created_by_user_id, title, description, skills, role_category, location, status, created_at, updated_at)
                VALUES (1, 2, 1, 'Cybersecurity Research Intern', 'Research modern application vulnerability analysis.', 'Python, C, Cryptography', 'backend', 'Bengaluru', 'active', ?, ?)
            """, (now, now))
            db.commit()

        # 5. Student Alice registers and links to DSATM with consent withheld
        clientAlice = Client(port)
        status, _, body = clientAlice.request('/register')
        clientAlice.request('/register', 'POST', {
            'name': 'Alice Private',
            'email': 'alice.private@student.test',
            'password': 'StudentPassword123!',
            'csrf_token': token(body)
        })

        # Alice logs in
        status, _, body = clientAlice.request('/login')
        clientAlice.request('/login', 'POST', {
            'email': 'alice.private@student.test',
            'password': 'StudentPassword123!',
            'csrf_token': token(body)
        })

        status, _, body = clientAlice.request('/profile')
        check('student profile page renders college dropdown', status == 200 and 'Dayananda Sagar Academy' in body)
        csrf = token(body)
        clientAlice.request('/profile', 'POST', {
            'college': 'DSATM Bengaluru',
            'skills': 'Cybersecurity, Python',
            'bio': 'Focused on systems security.',
            'github_username': 'alice-sec',
            'college_org_id': '1',
            'csrf_token': csrf
            # Note: college_share_consent omitted -> default 0
        })

        # 6. Student Bob registers, links to DSATM with consent granted, and applies to job
        clientBob = Client(port)
        status, _, body = clientBob.request('/register')
        clientBob.request('/register', 'POST', {
            'name': 'Bob Consenting',
            'email': 'bob.consenting@student.test',
            'password': 'StudentPassword123!',
            'csrf_token': token(body)
        })

        # Bob logs in
        status, _, body = clientBob.request('/login')
        clientBob.request('/login', 'POST', {
            'email': 'bob.consenting@student.test',
            'password': 'StudentPassword123!',
            'csrf_token': token(body)
        })

        status, _, body = clientBob.request('/profile')
        csrf = token(body)
        clientBob.request('/profile', 'POST', {
            'college': 'DSATM Bengaluru',
            'skills': 'Python, Cryptography',
            'bio': 'Aspiring security researcher.',
            'github_username': 'bob-crypto',
            'college_org_id': '1',
            'college_share_consent': '1',
            'csrf_token': csrf
        })

        # Bob creates resume
        status, _, body = clientBob.request('/resume')
        csrf = token(body)
        clientBob.request('/resume', 'POST', {
            'title': 'Bob Security Resume',
            'summary': 'Undergraduate specializing in applied security.',
            'skills': 'Python, Cryptography, Linux',
            'csrf_token': csrf
        })

        # Bob applies for the job
        status, _, body = clientBob.request('/jobs/view?id=1')
        csrf = token(body)
        clientBob.request('/jobs/apply', 'POST', {
            'job_id': '1',
            'notes': 'Excited about the security lab.',
            'csrf_token': csrf
        })

        # 7. Student attempting to access college portal is denied (403)
        check('student accessing college dashboard denied (403)', clientBob.request('/college/dashboard')[0] == 403)

        # 8. College placement officer logs in at /college/login
        clientOfficer = Client(port)
        status, _, body = clientOfficer.request('/college/login')
        csrf = token(body)
        login_status, login_headers, _ = clientOfficer.request('/college/login', 'POST', {
            'email': 'placement@dsatm.test',
            'password': 'PlacementPassword123!',
            'csrf_token': csrf
        })
        check('college officer login succeeds with 303 redirect', login_status == 303 and login_headers.get('location') == '/college/dashboard')

        # 9. College placement officer views dashboard
        status, _, body = clientOfficer.request('/college/dashboard')
        check('college dashboard renders successfully (200)', status == 200 and 'Campus Placement Intelligence' in body)
        check('dashboard displays college name', 'Dayananda Sagar Academy' in body)
        check('dashboard displays Associated Students metric', 'Associated Students' in body)
        check('dashboard displays Consented vs Confidential badges', 'Consented' in body and 'Confidential' in body)
        check('consenting student email is displayed', 'bob.consenting@student.test' in body)
        check('non-consenting student email is masked', 'Consent Withheld' in body)

        # 10. Placement officer views Bob's placement details -> 200 OK
        with sqlite3.connect(db_path) as db:
            cur = db.execute("SELECT id FROM users WHERE email = 'bob.consenting@student.test'")
            bob_id = cur.fetchone()[0]
            cur = db.execute("SELECT id FROM users WHERE email = 'alice.private@student.test'")
            alice_id = cur.fetchone()[0]

        status, _, body = clientOfficer.request(f'/college/students/view?id={bob_id}')
        check('view consenting student placement details succeeds (200)', status == 200 and 'Student Placement Record' in body)
        check('consenting student applications listed', 'Cybersecurity Research Intern' in body)
        check('consenting student resume summary listed', 'Bob Security Resume' in body)

        # 11. Placement officer views Alice's placement details -> 403 Forbidden (Consent Withheld)
        status, _, body = clientOfficer.request(f'/college/students/view?id={alice_id}')
        check('view non-consenting student details rejected with 403', status == 403 and 'Student has not consented' in body)

        # 12. Cross-college / non-existent student lookup -> 404 Not Found
        status, _, body = clientOfficer.request('/college/students/view?id=99999')
        check('foreign student lookup returns 404', status == 404 and 'Student not found in your college roster' in body)

        # 13. College placement officer logs out
        status, _, body = clientOfficer.request('/college/dashboard')
        csrf = token(body)
        logout_status, logout_headers, _ = clientOfficer.request('/logout', 'POST', {
            'csrf_token': csrf
        })
        check('college officer logout redirects with 303', logout_status == 303 and logout_headers.get('location') == '/login')
        check('subsequent access to college dashboard denied (401)', clientOfficer.request('/college/dashboard')[0] == 401)

    finally:
        stop()
        if os.path.exists(log.name):
            os.unlink(log.name)

print('====================================================================')
print(f'COLLEGE HTTP SMOKE RESULT: {passed} passed, {failed} failed')
print('====================================================================')
if failed > 0:
    exit(1)
