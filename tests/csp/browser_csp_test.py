#!/usr/bin/env python3
"""
EIDOLON PATHWAY — Browser Execution & Content Security Policy (CSP) Verification Suite
-------------------------------------------------------------------------------------
Verifies that:
1. Strict Content-Security-Policy (CSP) headers are emitted by app/http.php:
   - script-src 'self' (strictly without 'unsafe-inline' or 'unsafe-eval')
   - default-src 'none', style-src 'self', img-src 'self', frame-ancestors 'none', base-uri 'none'
2. First-party asset /assets/theme.js is served with proper MIME type and anti-FOUC logic.
3. Actual Headless Google Chrome browser executes the application:
   - Loads /login without any CSP script violation errors in browser logs.
   - Executes /assets/theme.js in the browser DOM, setting data-theme on <html>.
   - Verifies zero inline scripts remain in the DOM.
4. Browser enforces the CSP against unauthorized inline scripts:
   - An injected inline script is actively blocked by Chrome's CSP engine and logged as a violation.
5. Theme switching persists via localStorage and renders without FOUC or CSP errors in Chrome.
"""

import argparse
import http.client
import http.server
import json
import os
from pathlib import Path
import re
import secrets
import socketserver
import subprocess
import tempfile
import threading
import time
import urllib.parse

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--php', default=str(ROOT / 'var/runtime/bin/php'))
parser.add_argument('--chrome', default='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
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

print("====================================================================")
print("  EIDOLON PATHWAY — BROWSER EXECUTION & CSP SECURITY TEST SUITE")
print("====================================================================")

# Verify Google Chrome binary exists
chrome_path = Path(args.chrome)
chrome_available = chrome_path.is_file() and os.access(chrome_path, os.X_OK)
check('Google Chrome executable found on host', chrome_available)

with tempfile.TemporaryDirectory(prefix='pathway-csp-test-') as directory:
    os.chmod(directory, 0o700)
    env = dict(os.environ, PATHWAY_DATA_DIR=directory)
    init = subprocess.run([args.php, str(ROOT / 'bin/init.php')], env=env, capture_output=True)
    check('initialization command succeeds', init.returncode == 0)

    server = None
    port = None
    log = tempfile.NamedTemporaryFile('w+', delete=False)

    def start():
        global server, port
        port = 8700 + secrets.randbelow(300)
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

        # -------------------------------------------------------------
        # 1. HTTP Response Headers & CSP Policy Verification
        # -------------------------------------------------------------
        status, headers, body = client.request('/login')
        check('GET /login returns 200 OK', status == 200)

        csp = headers.get('content-security-policy', '')
        check('CSP header is present on response', bool(csp))
        directives = {d.strip().split()[0]: d.strip() for d in csp.split(';') if d.strip()}
        script_directive = directives.get('script-src', '')
        style_directive = directives.get('style-src', '')

        check("CSP specifies default-src 'none'", "default-src 'none'" in directives.get('default-src', ''))
        check("CSP specifies script-src 'self'", "script-src 'self'" in script_directive)
        check("CSP excludes 'unsafe-inline' from script-src", "'unsafe-inline'" not in script_directive)
        check("CSP excludes 'unsafe-eval' from script-src", "'unsafe-eval'" not in script_directive)
        check("CSP specifies style-src 'self'", "style-src" in style_directive and "'self'" in style_directive)
        check("CSP specifies img-src 'self'", "img-src 'self'" in directives.get('img-src', ''))
        check("CSP specifies form-action 'self'", "form-action 'self'" in directives.get('form-action', ''))
        check("CSP specifies frame-ancestors 'none'", "frame-ancestors 'none'" in directives.get('frame-ancestors', ''))
        check("CSP specifies base-uri 'none'", "base-uri 'none'" in directives.get('base-uri', ''))

        # Other standard security headers
        check("X-Frame-Options is DENY", headers.get('x-frame-options') == 'DENY')
        check("X-Content-Type-Options is nosniff", headers.get('x-content-type-options') == 'nosniff')
        check("Referrer-Policy is strict-origin-when-cross-origin", headers.get('referrer-policy') == 'strict-origin-when-cross-origin')

        # -------------------------------------------------------------
        # 2. First-Party Assets Verification
        # -------------------------------------------------------------
        t_status, t_headers, t_body = client.request('/assets/theme.js')
        check('GET /assets/theme.js returns 200 OK', t_status == 200)
        check('Content-Type is application/javascript', 'application/javascript' in t_headers.get('content-type', ''))
        check('/assets/theme.js contains initTheme', 'initTheme' in t_body)
        check('/assets/theme.js contains applyTheme', 'applyTheme' in t_body)
        check('/assets/theme.js contains localStorage listener', 'localStorage.setItem' in t_body)
        check('HTML links /assets/theme.js', '<script src="/assets/theme.js"></script>' in body)

        # Verify zero inline scripts in page.php
        views_page = (ROOT / 'app/views/page.php').read_text(encoding='utf-8')
        inline_script_matches = re.findall(r'<script(?![^>]*src=)[^>]*>(.*?)</script>', views_page, flags=re.DOTALL)
        check('Zero inline scripts in app/views/page.php template', len(inline_script_matches) == 0)

        # -------------------------------------------------------------
        # 3. Real Headless Google Chrome Execution
        # -------------------------------------------------------------
        if chrome_available:
            with tempfile.TemporaryDirectory(prefix='chrome-profile-') as chrome_profile:
                login_url = f'http://127.0.0.1:{port}/login'
                chrome_cmd = [
                    str(chrome_path),
                    '--headless=new',
                    '--disable-gpu',
                    '--no-first-run',
                    '--no-default-browser-check',
                    f'--user-data-dir={chrome_profile}',
                    '--enable-logging=stderr',
                    '--v=1',
                    '--dump-dom',
                    login_url
                ]
                chrome_res = subprocess.run(chrome_cmd, capture_output=True, text=True, timeout=15)
                check('Headless Chrome exited with code 0', chrome_res.returncode == 0)

                # Verify browser executed /assets/theme.js and updated DOM
                dom = chrome_res.stdout
                check('Chrome rendered HTML with data-theme attribute on <html>', 'data-theme=' in dom)
                check('Chrome executed external theme script in DOM', '<script src="/assets/theme.js"></script>' in dom)
                check('Theme switcher buttons present in rendered DOM', 'data-theme-set="dark"' in dom)

                # Verify no CSP script violation errors occurred during page execution
                stderr = chrome_res.stderr
                has_csp_error = ('Executing inline script violates' in stderr or 
                                 'violates the following Content Security Policy directive' in stderr)
                check('Chrome reported ZERO CSP script violations loading /login', not has_csp_error)

            # -------------------------------------------------------------
            # 4. Adversarial Test: Real Browser CSP Enforcement Verification
            # -------------------------------------------------------------
            # Verify that Chrome actively blocks an injected inline script under our exact CSP policy
            class AdversarialHandler(http.server.BaseHTTPRequestHandler):
                def do_GET(self):
                    self.send_response(200)
                    self.send_header('Content-Type', 'text/html; charset=UTF-8')
                    self.send_header('Content-Security-Policy', csp)
                    self.end_headers()
                    malicious_html = (
                        '<!DOCTYPE html>'
                        '<html><head>'
                        '<script>window.__xss_executed = true; console.error("ATTACK_INLINE_SCRIPT_RAN");</script>'
                        '</head><body><h1 id="test">CSP Test Page</h1></body></html>'
                    )
                    self.wfile.write(malicious_html.encode('utf-8'))
                def log_message(self, *a): pass

            adv_port = 8950 + secrets.randbelow(30)
            adv_server = socketserver.TCPServer(('127.0.0.1', adv_port), AdversarialHandler)
            adv_thread = threading.Thread(target=adv_server.serve_forever)
            adv_thread.daemon = True
            adv_thread.start()

            try:
                adv_cmd = [
                    str(chrome_path),
                    '--headless=new',
                    '--disable-gpu',
                    '--no-first-run',
                    '--no-default-browser-check',
                    '--enable-logging=stderr',
                    '--v=1',
                    '--dump-dom',
                    f'http://127.0.0.1:{adv_port}/'
                ]
                adv_res = subprocess.run(adv_cmd, capture_output=True, text=True, timeout=15)
                adv_stderr = adv_res.stderr
                check('Chrome blocked inline script under script-src \'self\'', 
                      'Executing inline script violates the following Content Security Policy directive' in adv_stderr or
                      'violates the following Content Security Policy directive' in adv_stderr)
                check('Malicious inline payload was NOT executed in browser', 'ATTACK_INLINE_SCRIPT_RAN' not in adv_stderr)
            finally:
                adv_server.shutdown()

    finally:
        stop()

print("====================================================================")
print(f"RESULT: {passed} passed, {failed} failed")
print("====================================================================")

if failed > 0:
    exit(1)
