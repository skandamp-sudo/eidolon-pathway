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

with tempfile.TemporaryDirectory(prefix='pathway-resume-http-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8200 + secrets.randbelow(800)
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
        clientAlice = Client(port)

        # 1. Anonymous access denied
        check('anonymous resume editor denied', clientAlice.request('/resume')[0] == 401)
        check('anonymous resume preview denied', clientAlice.request('/resume/preview?id=1')[0] == 401)

        # 2. Register and log in Alice
        status, _, body = clientAlice.request('/register')
        reg_csrf = token(body)
        alice_creds = {'email': 'alice-resume@test.edu', 'password': 'password123456'}
        reg_status, _, _ = clientAlice.request('/register', 'POST', dict(alice_creds, name='Alice Innovator', csrf_token=reg_csrf))
        check('Alice registration succeeds', reg_status == 303)

        status, _, body = clientAlice.request('/login')
        log_status, _, _ = clientAlice.request('/login', 'POST', dict(alice_creds, csrf_token=token(body)))
        check('Alice login succeeds', log_status == 303)

        # 3. View blank resume editor
        r_status, _, r_body = clientAlice.request('/resume')
        check('resume builder page loads', r_status == 200 and 'Build Your Verifiable Resume' in r_body)
        r_csrf = token(r_body)

        # 4. Missing CSRF rejected
        check('save resume missing CSRF rejected', clientAlice.request('/resume', 'POST', {'title': 'Draft 1'})[0] == 403)

        # 5. Seed a completed assessment for Alice directly in SQLite
        db_path = Path(directory) / 'pathway.sqlite'
        with sqlite3.connect(db_path) as db:
            now = int(time.time())
            db.execute("""
                INSERT INTO assessments (id, user_id, repository, target_role, rubric_version, state, commit_sha, created_at, updated_at)
                VALUES (1, 1, 'alice/micro-kernel', 'backend', '2026.1', 'completed', 'c0ffee1234567890abcdef1234567890abcdef12', ?, ?)
            """, (now, now))
            db.execute("INSERT INTO improvement_tasks (assessment_id, priority, finding_id, title, acceptance) VALUES (1, 1, 'f1', 'Refactor IPC channels', 'Eliminate raw memory copy')")
            db.commit()

        # 6. Save a complete resume draft with assessment citation
        resume_data = {
            'csrf_token': r_csrf,
            'title': 'High Assurance Systems Engineer',
            'assessment_id': '1',
            'summary': 'Specialized in microkernel design, formal validation, and deterministic SQLite engines.',
            'skills': 'C, PHP 8.4, SQLite, Rust, Formal Methods',
            'education_institution[]': ['MIT'],
            'education_degree[]': ['B.S. in Computer Science'],
            'education_year[]': ['2022 – 2026'],
            'education_gpa[]': ['3.98'],
            'experience_company[]': ['Pathway Labs'],
            'experience_role[]': ['Security Intern'],
            'experience_start[]': ['May 2025'],
            'experience_end[]': ['Aug 2025'],
            'experience_desc[]': ['Constructed commit-pinned audit pipelines'],
            'project_name[]': ['MicroKernel V1'],
            'project_tech[]': ['C, Assembly'],
            'project_desc[]': ['Zero-alloc memory safety guarantees'],
            'project_link[]': ['https://github.com/alice/micro-kernel'],
        }
        save_status, save_headers, _ = clientAlice.request('/resume', 'POST', resume_data)
        check('save resume redirects with 303', save_status == 303 and '/resume?id=1' in save_headers.get('location', ''))

        # 7. Reopen saved draft
        reopen_status, _, reopen_body = clientAlice.request('/resume?id=1')
        check('reopened draft displays saved title', reopen_status == 200 and 'High Assurance Systems Engineer' in reopen_body)
        check('reopened draft displays saved skills', 'Formal Methods' in reopen_body and 'MicroKernel V1' in reopen_body)

        # 8. ATS Print Preview
        prev_status, _, prev_body = clientAlice.request('/resume/preview?id=1')
        check('ATS preview loads with 200', prev_status == 200)
        check('ATS preview renders candidate header', 'Alice Innovator' in prev_body and 'alice-resume@test.edu' in prev_body)
        check('ATS preview renders professional summary', 'Specialized in microkernel design' in prev_body)
        check('ATS preview renders verified assessment citation', 'alice/micro-kernel' in prev_body and 'c0ffee123456' in prev_body and 'Refactor IPC channels' in prev_body)

        # 9. IDOR Verification: Register Bob and attempt access to Alice's resume
        clientBob = Client(port)
        _, _, b_body = clientBob.request('/register')
        bob_creds = {'email': 'bob-adversary@test.edu', 'password': 'password123456'}
        clientBob.request('/register', 'POST', dict(bob_creds, name='Bob Adversary', csrf_token=token(b_body)))
        _, _, b_login = clientBob.request('/login')
        clientBob.request('/login', 'POST', dict(bob_creds, csrf_token=token(b_login)))

        # Bob attempts GET /resume?id=1 (Alice's resume) -> must be 404
        check('IDOR: Bob denied viewing Alice draft in editor (404)', clientBob.request('/resume?id=1')[0] == 404)

        # Bob attempts GET /resume/preview?id=1 (Alice's preview) -> must be 404
        check('IDOR: Bob denied viewing Alice ATS preview (404)', clientBob.request('/resume/preview?id=1')[0] == 404)

        # Bob attempts POST /resume with id=1 to overwrite Alice's resume
        _, _, b_resume_page = clientBob.request('/resume')
        bob_forge = {
            'csrf_token': token(b_resume_page),
            'id': '1',  # Alice's resume ID
            'title': 'Compromised by Bob',
            'summary': 'Overwritten text',
        }
        b_post_status, b_post_headers, _ = clientBob.request('/resume', 'POST', bob_forge)
        # Should redirect back with flash error
        check('IDOR: Bob update attempt redirected with error', b_post_status == 303)

        # Verify Alice's resume remained untouched in SQLite
        with sqlite3.connect(db_path) as db:
            title = db.execute("SELECT title FROM resumes WHERE id = 1").fetchone()[0]
        check('Alice resume was not modified by Bob attack', title == 'High Assurance Systems Engineer')

    finally:
        stop()
        log.close()

print(f'RESULT: {passed} passed, {failed} failed')
raise SystemExit(1 if failed else 0)
