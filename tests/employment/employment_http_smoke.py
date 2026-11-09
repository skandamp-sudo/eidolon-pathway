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
        raise RuntimeError('Expected form token missing from body.')
    return match.group(1)

with tempfile.TemporaryDirectory(prefix='pathway-emp-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8100 + secrets.randbelow(800)
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
        client = Client(port)

        # 1. Anonymous access to private employment endpoints denied
        check('anonymous profile denied', client.request('/profile')[0] == 401)
        check('anonymous applications denied', client.request('/applications')[0] == 401)

        # 2. Register a student
        status, _, body = client.request('/register')
        reg_csrf = token(body)
        creds = {'email': 'student-emp@test.edu', 'password': 'password123456'}
        reg_status, reg_headers, _ = client.request('/register', 'POST', dict(creds, name='Alice Walker', csrf_token=reg_csrf))
        check('student registration succeeds', reg_status == 303)

        # 3. Log in as student
        status, _, body = client.request('/login')
        login_csrf = token(body)
        log_status, log_headers, _ = client.request('/login', 'POST', dict(creds, csrf_token=login_csrf))
        check('student login succeeds', log_status == 303 and log_headers.get('location') == '/dashboard')

        # 4. View profile
        p_status, _, p_body = client.request('/profile')
        check('profile page loads for authenticated student', p_status == 200 and 'Student Engineering Profile' in p_body)
        p_csrf = token(p_body)

        # 5. Profile CSRF rejection
        check('profile update missing CSRF rejected', client.request('/profile', 'POST', {'college': 'MIT'})[0] == 403)

        # 6. Valid profile update
        profile_data = {
            'csrf_token': p_csrf,
            'college': 'Stanford University',
            'skills': 'PHP, SQLite, TypeScript, React',
            'bio': 'Passionate about verifiable code artifacts and systems.',
            'github_username': 'alice-walker',
        }
        u_status, u_headers, _ = client.request('/profile', 'POST', profile_data)
        check('profile update redirects with 303', u_status == 303 and u_headers.get('location') == '/profile')

        # Verify profile updated in GET
        _, _, p_body_updated = client.request('/profile')
        check('updated profile fields displayed', 'Stanford University' in p_body_updated and 'alice-walker' in p_body_updated)

        # 7. Seed an active verified company and job in SQLite directly
        db_path = Path(directory) / 'pathway.sqlite'
        with sqlite3.connect(db_path) as db:
            now = int(time.time())
            # Insert active company
            db.execute("INSERT INTO organizations (id, name, slug, type, status, created_at, updated_at) VALUES (1, 'Acme Systems', 'acme-systems', 'COMPANY', 'ACTIVE', ?, ?)", (now, now))
            # Insert company member user
            db.execute("INSERT INTO users (id, name, email, password_hash, role) VALUES (2, 'Acme Admin', 'admin@acme.test', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'company')")
            db.execute("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (1, 2, 'OWNER', 'ACTIVE', ?, ?)", (now, now))
            # Insert active job
            db.execute("INSERT INTO jobs (id, organization_id, created_by_user_id, title, description, skills, role_category, location, status, created_at, updated_at) VALUES (1, 1, 2, 'Systems Software Intern', 'Build verified SQLite extensions and runtime tools for high assurance platforms.', 'C, PHP, SQLite', 'backend', 'Remote', 'active', ?, ?)", (now, now))
            db.commit()

        # 8. Browse jobs
        j_status, _, j_body = client.request('/jobs')
        check('jobs catalog displays active verified job', j_status == 200 and 'Systems Software Intern' in j_body and 'Acme Systems' in j_body)

        # 9. Filter jobs by category
        jf_status, _, jf_body = client.request('/jobs?category=backend')
        check('jobs filter returns matching role', jf_status == 200 and 'Systems Software Intern' in jf_body)
        jf_none, _, jf_none_body = client.request('/jobs?category=frontend')
        check('jobs filter excludes non-matching role', 'No active job listings match your filters' in jf_none_body)

        # 10. View job details
        jd_status, _, jd_body = client.request('/jobs/view?id=1')
        check('job detail page loads', jd_status == 200 and 'Systems Software Intern' in jd_body and 'Submit Application' in jd_body)
        app_csrf = token(jd_body)

        # 11. Apply for job
        apply_data = {
            'csrf_token': app_csrf,
            'job_id': '1',
            'notes': 'Excited to build verifiable SQLite tooling for Acme Systems.'
        }
        a_status, a_headers, _ = client.request('/jobs/apply', 'POST', apply_data)
        check('job application redirects to applications list', a_status == 303 and a_headers.get('location') == '/applications')

        # 12. Check applications list
        apps_status, _, apps_body = client.request('/applications')
        check('submitted application listed in tracking table', apps_status == 200 and 'Acme Systems' in apps_body and 'Systems Software Intern' in apps_body and 'Under Review' in apps_body)

        # 13. Duplicate application prevention over HTTP
        dup_status, dup_headers, _ = client.request('/jobs/apply', 'POST', apply_data)
        check('duplicate application redirects back to job with error', dup_status == 303 and dup_headers.get('location') == '/jobs/view?id=1')

        # 14. Check job detail page now shows "Application Already Submitted"
        _, _, jd_revisit = client.request('/jobs/view?id=1')
        check('job detail displays already applied banner', 'Application Already Submitted' in jd_revisit and 'Current Status: Applied' in jd_revisit)

    finally:
        stop()
        log.close()

print(f'RESULT: {passed} passed, {failed} failed')
raise SystemExit(1 if failed else 0)
