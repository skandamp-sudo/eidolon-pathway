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

# 1. Static CSS verification for 3-theme design system
css_path = ROOT / 'public/assets/pathway.css'
css_content = css_path.read_text(encoding='utf-8')
check('CSS defines html[data-theme="light"]', 'html[data-theme="light"]' in css_content)
check('CSS defines html[data-theme="dark"]', 'html[data-theme="dark"]' in css_content)
check('CSS defines @media (prefers-color-scheme: light)', '@media (prefers-color-scheme: light)' in css_content)
check('CSS defines .theme-switcher component', '.theme-switcher' in css_content and '.theme-btn' in css_content)

with tempfile.TemporaryDirectory(prefix='pathway-theme-intel-http-') as directory:
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

        # Database direct seeding for admin, college, and company
        db_path = Path(directory) / 'pathway.sqlite'
        conn = sqlite3.connect(str(db_path))
        conn.row_factory = sqlite3.Row
        cur = conn.cursor()
        now = int(time.time())

        # Seed admin
        admin_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("AdminPass123!", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()
        cur.execute("INSERT INTO users (name, email, password_hash, role) VALUES ('Admin', 'admin@test.internal', ?, 'admin')", (admin_hash,))
        admin_id = cur.lastrowid

        # Seed company
        cur.execute("INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at) VALUES ('Acme Labs', 'acme-labs', 'COMPANY', 'ACTIVE', ?, ?, ?)", (admin_id, now, now))
        company_id = cur.lastrowid
        cur.execute("INSERT INTO users (name, email, password_hash, role) VALUES ('Recruiter', 'recruiter@acme.test', ?, 'company')", (admin_hash,))
        recruiter_id = cur.lastrowid
        cur.execute("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, 'OWNER', 'ACTIVE', ?, ?)", (company_id, recruiter_id, now, now))

        # Seed job opening
        cur.execute("INSERT INTO jobs (organization_id, title, description, skills, role_category, location, status, created_by_user_id, created_at, updated_at) VALUES (?, 'Senior Backend Engineer', 'Develop high performance distributed systems.', 'Python, SQLite, REST, Docker', 'backend', 'Bengaluru', 'active', ?, ?, ?)", (company_id, recruiter_id, now, now))
        job_id = cur.lastrowid

        # Seed college
        cur.execute("INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at) VALUES ('Engineering College', 'eng-college', 'COLLEGE', 'ACTIVE', ?, ?, ?)", (admin_id, now, now))
        college_id = cur.lastrowid
        cur.execute("INSERT INTO users (name, email, password_hash, role) VALUES ('Dean', 'dean@college.test', ?, 'college')", (admin_hash,))
        dean_id = cur.lastrowid
        cur.execute("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, 'OWNER', 'ACTIVE', ?, ?)", (college_id, dean_id, now, now))

        conn.commit()

        # 2. Page HTML head theme script, CSP compliance, and switcher
        anon = Client(port)
        status, headers, body = anon.request('/login')
        check('GET /login loads successfully (200)', status == 200)
        csp = headers.get('content-security-policy', '')
        directives = {d.strip().split()[0]: d.strip() for d in csp.split(';') if d.strip()}
        script_dir = directives.get('script-src', '')
        check('CSP specifies script-src \'self\'', "script-src 'self'" in script_dir)
        check('CSP excludes unsafe-inline for scripts', "'unsafe-inline'" not in script_dir)
        check('HTML head links external first-party theme script', '<script src="/assets/theme.js"></script>' in body)
        check('HTML has no inline <script> tags', '<script>' not in body and '<script ' not in body.replace('<script src="/assets/theme.js"></script>', ''))

        # Verify /assets/theme.js serves properly and contains anti-FOUC logic
        t_status, t_headers, t_body = anon.request('/assets/theme.js')
        check('GET /assets/theme.js returns 200', t_status == 200)
        check('/assets/theme.js served as javascript', 'javascript' in t_headers.get('content-type', ''))
        check('/assets/theme.js contains anti-FOUC localStorage theme logic', "localStorage.getItem('pathway-theme')" in t_body)

        check('HTML renders theme switcher with 3 options', 'data-theme-set="light"' in body and 'data-theme-set="system"' in body and 'data-theme-set="dark"' in body)
        check('HTML body contains skip-link for accessibility', 'class="skip-link"' in body)

        # 3. Student Registration & Login
        student = Client(port)
        status, headers, body = student.request('/register')
        r_csrf = token(body)
        student.request('/register', method='POST', fields={
            'csrf_token': r_csrf,
            'name': 'Dev Student',
            'email': 'dev@student.test',
            'password': 'StudentSecret123!'
        })
        status, headers, body = student.request('/login')
        l_csrf = token(body)
        student.request('/login', method='POST', fields={
            'csrf_token': l_csrf,
            'email': 'dev@student.test',
            'password': 'StudentSecret123!'
        })

        # 4. Student Dashboard Real Career Analytics
        status, headers, body = student.request('/dashboard')
        check('Student /dashboard loads (200)', status == 200)
        check('Dashboard displays Career Readiness section', 'Career Readiness' in body)
        check('Dashboard displays Applications metric', 'Application' in body)
        check('Dashboard displays Portfolio Assessments metric', 'Assessment' in body)
        check('Dashboard displays Credentials metric', 'Credential' in body)

        # 5. Build and Preview Resume with ATS Intelligence
        status, headers, body = student.request('/resume')
        check('GET /resume loads (200)', status == 200)
        res_csrf = token(body)

        student.request('/resume', method='POST', fields={
            'csrf_token': res_csrf,
            'title': 'ATS Optimized Resume',
            'full_name': 'Dev Student',
            'email': 'dev@student.test',
            'phone': '+91 99887 76655',
            'location': 'Bengaluru',
            'summary': 'Passionate backend engineer with expertise in Python microservices, SQLite architecture, and automated verification. Implemented robust REST APIs and optimized database query latency.',
            'institution[]': 'Engineering College',
            'degree[]': 'B.Tech Information Science',
            'grad_year[]': '2026',
            'edu_details[]': 'GPA 9.0',
            'company[]': 'Tech Labs',
            'role[]': 'Software Intern',
            'start_date[]': '2024-01',
            'end_date[]': '2024-06',
            'exp_desc[]': 'Implemented SQLite database caching.\nArchitected REST endpoints.\nOptimized backend query execution.',
            'project_name[]': 'Distributed Cache',
            'project_tech[]': 'Python, SQLite',
            'project_desc[]': 'Developed fast in-memory store.',
            'skills': 'Python, SQLite, REST, Docker, Git, FastAPI',
        })

        # Find created resume ID
        cur.execute("SELECT id FROM resumes WHERE title = 'ATS Optimized Resume'")
        resume_row = cur.fetchone()
        resume_id = resume_row['id']

        # 6. Preview Resume and verify ATS Intelligence panel
        status, headers, body = student.request(f'/resume/preview?id={resume_id}')
        check('GET /resume/preview loads (200)', status == 200)
        check('Preview displays ATS Parser Analysis &amp; Keyword Alignment', 'ATS Parser Analysis &amp; Keyword Alignment' in body)
        check('Preview displays ATS Readiness Score', '/ 100' in body)
        check('Preview renders Action-Oriented Bullet Points checklist', 'Action-Oriented Bullet Points' in body)
        check('Preview renders Matched Industry Keywords', 'Matched Industry Keywords' in body)
        check('Preview renders transparent scoring disclaimer', 'ATS analysis provides transparent rule-based guidance' in body)

        # Benchmark against specific job
        status, headers, body = student.request(f'/resume/preview?id={resume_id}&job_id={job_id}')
        check('Preview with job_id benchmarks against vacancy', 'Senior Backend Engineer' in body)

        # 7. Company Dashboard Pipeline Analytics
        recruiter = Client(port)
        status, headers, body = recruiter.request('/company/login')
        c_csrf = token(body)
        recruiter.request('/company/login', method='POST', fields={
            'csrf_token': c_csrf,
            'email': 'recruiter@acme.test',
            'password': 'AdminPass123!',
        })
        status, headers, body = recruiter.request('/company/dashboard')
        check('Company dashboard loads (200)', status == 200)
        check('Company dashboard displays Total Positions', 'Total Positions' in body)
        check('Company dashboard displays Total Applicants', 'Total Applicants' in body)
        check('Company dashboard displays conversion rate metric', 'conversion rate' in body)

        # 8. College Dashboard Cohort Analytics & Safeguards
        dean = Client(port)
        status, headers, body = dean.request('/college/login')
        col_csrf = token(body)
        dean.request('/college/login', method='POST', fields={
            'csrf_token': col_csrf,
            'email': 'dean@college.test',
            'password': 'AdminPass123!',
        })
        status, headers, body = dean.request('/college/dashboard')
        check('College dashboard loads (200)', status == 200)
        check('College dashboard displays Associated Students', 'Associated Students' in body)
        check('College dashboard displays Consent Granted', 'Consent Granted' in body)
        check('College dashboard displays Consent-based privacy safeguards active', 'Consent-based privacy safeguards active' in body)

    finally:
        stop()

print(f'\nRESULT: {passed} passed, {failed} failed')
if failed > 0:
    exit(1)
