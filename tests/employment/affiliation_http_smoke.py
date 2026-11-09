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

with tempfile.TemporaryDirectory(prefix='pathway-affiliation-http-') as directory:
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

        # Create platform admin
        admin_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("AdminPass#2026", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()
        cur.execute("INSERT INTO users (name, email, password_hash, role, created_at) VALUES ('Admin', 'admin@pathway.test', ?, 'admin', ?)", (admin_hash, now))
        admin_id = cur.lastrowid

        # Create approved college
        cur.execute("INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at) VALUES ('DSATM Bengaluru', 'dsatm-bengaluru', 'COLLEGE', 'ACTIVE', ?, ?, ?)", (admin_id, now, now))
        college_id = cur.lastrowid

        # College staff user
        officer_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("OfficerPass#2026", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()
        cur.execute("INSERT INTO users (name, email, password_hash, role, created_at) VALUES ('Placement Officer', 'officer@dsatm.test', ?, 'college', ?)", (officer_hash, now))
        officer_id = cur.lastrowid
        cur.execute("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, 'STAFF', 'ACTIVE', ?, ?)", (college_id, officer_id, now, now))

        # Create approved company
        cur.execute("INSERT INTO organizations (name, slug, type, status, created_by_user_id, created_at, updated_at) VALUES ('Eidolon AI Systems', 'eidolon-ai', 'COMPANY', 'ACTIVE', ?, ?, ?)", (admin_id, now, now))
        company_id = cur.lastrowid

        # Company recruiter user
        recruiter_hash = subprocess.run(
            [args.php, '-r', 'echo password_hash("RecruiterPass#2026", PASSWORD_DEFAULT);'],
            capture_output=True, text=True
        ).stdout.strip()
        cur.execute("INSERT INTO users (name, email, password_hash, role, created_at) VALUES ('Recruiter Jane', 'recruiter@eidolon.test', ?, 'company', ?)", (recruiter_hash, now))
        recruiter_id = cur.lastrowid
        cur.execute("INSERT INTO organization_memberships (organization_id, user_id, role, status, created_at, updated_at) VALUES (?, ?, 'STAFF', 'ACTIVE', ?, ?)", (company_id, recruiter_id, now, now))

        # Job
        cur.execute("""
            INSERT INTO jobs (organization_id, created_by_user_id, title, description, skills, role_category, location, status, created_at, updated_at)
            VALUES (?, ?, 'AI Systems Intern', 'Build high-assurance distributed systems.', 'Python, PHP, Security', 'backend', 'Bengaluru', 'active', ?, ?)
        """, (company_id, recruiter_id, now, now))
        job_id = cur.lastrowid

        conn.commit()
        conn.close()

        # Test anonymous access protection
        anon = Client(port)
        status, _, _ = anon.request('/affiliation/request')
        check('anonymous /affiliation/request denied', status in (401, 403))

        status, _, _ = anon.request('/privacy/settings')
        check('anonymous /privacy/settings denied', status in (401, 403))

        status, _, _ = anon.request('/achievements')
        check('anonymous /achievements denied', status in (401, 403))

        status, _, _ = anon.request('/college/affiliations')
        check('anonymous /college/affiliations denied', status in (401, 403))

        status, _, _ = anon.request('/company/talent')
        check('anonymous /company/talent denied', status in (401, 403))

        # Student workflow
        student = Client(port)
        status, _, body = student.request('/register')
        check('registration form available', status == 200)
        t = token(body)
        status, headers, _ = student.request('/register', 'POST', {
            'name': 'Alice Student',
            'email': 'alice.student@college.test',
            'password': 'StudentSecurePass#2026',
            'csrf_token': t,
        })
        check('student registered successfully', status == 303)

        status, _, body = student.request('/login')
        t = token(body)
        status, headers, _ = student.request('/login', 'POST', {
            'email': 'alice.student@college.test',
            'password': 'StudentSecurePass#2026',
            'csrf_token': t,
        })
        check('student login succeeds', status == 303)

        # GET /affiliation/request
        status, _, body = student.request('/affiliation/request')
        check('student loads affiliation request screen', status == 200 and 'Campus Institutional Affiliation' in body)
        check('approved college listed in select options', 'DSATM Bengaluru' in body)

        # POST /affiliation/request
        t = token(body)
        status, _, _ = student.request('/affiliation/request', 'POST', {
            'college_org_id': college_id,
            'enrollment_number': '1DT22CS042',
            'degree': 'B.E. Computer Science',
            'department': 'Computer Science & Engineering',
            'graduation_year': 2026,
            'verification_note': '7th semester engineering student.',
            'csrf_token': t,
        })
        check('affiliation request submitted', status == 303)

        status, _, body = student.request('/affiliation/request')
        check('affiliation request listed as PENDING', 'PENDING' in body and '1DT22CS042' in body)

        # Student cancels request
        t = token(body)
        req_match = re.search(r'name="request_id" value="(\d+)"', body)
        check('request_id found in cancellation form', req_match is not None)
        req_id = int(req_match.group(1))

        status, _, _ = student.request('/affiliation/cancel', 'POST', {
            'request_id': req_id,
            'csrf_token': t,
        })
        check('student cancels affiliation request', status == 303)

        status, _, body = student.request('/affiliation/request')
        check('request shows CANCELLED status', 'CANCELLED' in body)

        # Re-submit affiliation request
        t = token(body)
        status, _, _ = student.request('/affiliation/request', 'POST', {
            'college_org_id': college_id,
            'enrollment_number': '1DT22CS042',
            'degree': 'B.E. Computer Science',
            'department': 'Computer Science & Engineering',
            'graduation_year': 2026,
            'verification_note': 'Re-submitted for placement verification.',
            'csrf_token': t,
        })
        check('affiliation request re-submitted', status == 303)

        status, _, body = student.request('/affiliation/request')
        t = token(body)
        req_match = re.search(r'name="request_id" value="(\d+)"', body)
        new_req_id = int(req_match.group(1))

        # College placement officer workflow
        officer = Client(port)
        status, _, body = officer.request('/college/login')
        t_off = token(body)
        status, _, _ = officer.request('/college/login', 'POST', {
            'email': 'officer@dsatm.test',
            'password': 'OfficerPass#2026',
            'csrf_token': t_off,
        })
        check('college officer signs in', status == 303)

        # GET /college/affiliations
        status, _, body = officer.request('/college/affiliations')
        check('officer views affiliation review queue', status == 200 and 'Institutional Affiliation Review Queue' in body)
        check('pending student request present in queue', '1DT22CS042' in body and 'Alice Student' in body)

        # POST /college/affiliations/review
        t_off = token(body)
        status, _, _ = officer.request('/college/affiliations/review', 'POST', {
            'request_id': new_req_id,
            'decision': 'APPROVED',
            'reason': 'Verified enrollment with university registrar.',
            'csrf_token': t_off,
        })
        check('officer approves affiliation request', status == 303)

        status, _, body = officer.request('/college/affiliations')
        check('queue shows APPROVED request', 'APPROVED' in body and 'Verified enrollment with university registrar' in body)

        # Student privacy & consent controls
        status, _, body = student.request('/privacy/settings')
        check('student loads privacy settings', status == 200 and 'Privacy, Sharing &amp; Consent Controls' in body)

        # Update privacy settings: enable sharing and talent discovery
        t = token(body)
        status, _, _ = student.request('/privacy/settings', 'POST', {
            'college_share_consent': '1',
            'share_contact_details': '1',
            'share_resume': '1',
            'share_achievements': '1',
            'share_applications': '1',
            'talent_discovery_opt_in': '1',
            'csrf_token': t,
        })
        check('privacy preferences saved', status == 303)

        status, _, body = student.request('/privacy/settings')
        check('privacy settings reflect enabled consent', 'checked' in body)

        # GET /privacy/access-log
        status, _, body = student.request('/privacy/access-log')
        check('student loads sensitive access audit trail', status == 200 and 'Sensitive Record Access Audit History' in body)

        # Student achievements
        status, _, body = student.request('/achievements')
        check('student loads achievements dashboard', status == 200 and 'Student Achievements &amp; Credentials' in body)

        t = token(body)
        status, _, _ = student.request('/achievements', 'POST', {
            'category': 'certification',
            'title': 'Certified Secure Engineer',
            'issuer': 'Cyber Security Institute',
            'issue_date': 'October 2026',
            'credential_url': 'https://example.com/certs/cse-001',
            'description': 'Mastery of web application security and cryptography.',
            'csrf_token': t,
        })
        check('achievement created successfully', status == 303)

        status, _, body = student.request('/achievements')
        check('achievement appears on dashboard', 'Certified Secure Engineer' in body and 'Cyber Security Institute' in body)

        # Student applies to job
        status, _, body = student.request(f'/jobs/view?id={job_id}')
        t = token(body)
        status, _, _ = student.request('/jobs/apply', 'POST', {
            'job_id': job_id,
            'notes': 'Excited to apply for the AI Systems Intern role.',
            'csrf_token': t,
        })
        check('student applies to company job', status == 303)

        # Company Recruiter workflow
        recruiter = Client(port)
        status, _, body = recruiter.request('/company/login')
        t_rec = token(body)
        status, _, _ = recruiter.request('/company/login', 'POST', {
            'email': 'recruiter@eidolon.test',
            'password': 'RecruiterPass#2026',
            'csrf_token': t_rec,
        })
        check('recruiter logs in', status == 303)

        # GET /company/talent (Talent Discovery)
        status, _, body = recruiter.request('/company/talent')
        check('recruiter accesses talent discovery directory', status == 200 and 'Candidate Talent Discovery' in body)
        check('opted-in Alice appears in talent search results', 'Alice Student' in body)

        # GET /company/applicants & candidate dossier
        status, _, body = recruiter.request('/company/applicants')
        check('recruiter lists candidate applicants', status == 200 and 'Alice Student' in body)
        check('dossier link present in applicants table', 'View Candidate Dossier' in body)

        app_match = re.search(r'/company/candidate\?id=(\d+)', body)
        check('candidate dossier link has application ID', app_match is not None)
        app_id = int(app_match.group(1))

        # GET /company/candidate
        status, _, body = recruiter.request(f'/company/candidate?id={app_id}')
        check('recruiter views candidate dossier', status == 200 and 'Alice Student' in body)
        check('candidate achievements visible in dossier', 'Certified Secure Engineer' in body)

        # College officer revokes affiliation
        status, _, body = officer.request('/college/dashboard')
        t_off = token(body)
        student_match = re.search(r'name="student_id" value="(\d+)"', body)
        check('student_id found in college roster for revocation', student_match is not None)
        stud_id = int(student_match.group(1))

        status, _, _ = officer.request('/college/affiliations/revoke', 'POST', {
            'student_id': stud_id,
            'reason': 'Student transferred to external institution.',
            'csrf_token': t_off,
        })
        check('college officer revokes student affiliation', status == 303)

        status, _, body = officer.request('/college/dashboard')
        check('revoked student removed from college roster', 'Alice Student' not in body or 'No students have linked' in body)

    finally:
        stop()

print(f'\nRESULT: {passed} passed, {failed} failed')
exit(1 if failed else 0)
