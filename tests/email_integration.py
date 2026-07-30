#!/usr/bin/env python3
"""Isolated API/SMTP tests. No real mail, credentials, or installation data."""
import concurrent.futures
import contextlib
import json
import os
from pathlib import Path
import shutil
import signal
import socket
import socketserver
import sqlite3
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.request
import zipfile
import sys

REPO = Path(__file__).resolve().parent.parent
messages = []
fail_delivery = False


class SMTPRecorder(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 local fixture\r\n')
        authentication = 0
        recipients = []
        while line := self.rfile.readline():
            command = line.decode().rstrip('\r\n')
            if authentication:
                authentication -= 1
                response = b'334 UGFzc3dvcmQ=\r\n' if authentication else b'235 Authenticated\r\n'
            elif command.startswith('EHLO'):
                response = b'250 fixture\r\n'
            elif command == 'AUTH LOGIN':
                authentication = 2
                response = b'334 VXNlcm5hbWU=\r\n'
            elif command.startswith('RCPT TO:'):
                recipients.append(command)
                response = b'250 OK\r\n'
            elif command == 'DATA':
                self.wfile.write(b'354 Send data\r\n')
                body = []
                while (part := self.rfile.readline()) not in (b'.\r\n', b''):
                    body.append(part)
                messages.append((recipients[:], b''.join(body)))
                response = b'550 fixture rejection\r\n' if fail_delivery else b'250 Recorded\r\n'
            elif command == 'QUIT':
                self.wfile.write(b'221 Bye\r\n')
                break
            else:
                response = b'250 OK\r\n'
            self.wfile.write(response)


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


with tempfile.TemporaryDirectory(prefix='editcms-altcha-') as directory, socketserver.ThreadingTCPServer(('127.0.0.1', 0), SMTPRecorder) as smtp:
    root = Path(directory)
    app = root / '_edit'
    if len(sys.argv) > 1:
        # Optionally test the exact distribution ZIP, after inspecting its paths.
        with zipfile.ZipFile(sys.argv[1]) as archive:
            for entry in archive.namelist():
                assert not entry.startswith('/') and '..' not in Path(entry).parts
            archive.extractall(root)
    else:
        for folder in ('core', 'api', 'admin-api'):
            shutil.copytree(REPO / '_edit' / folder, app / folder)
        shutil.copytree(REPO / '_edit/admin-source/public/altcha', app / 'admin/altcha')
        shutil.copyfile(REPO / '_edit/admin-source/PUBLIC-API.md', app / 'admin/PUBLIC-API.md')
    (app / 'config.php').write_text("""<?php
define('EDIT_ENCRYPTION_KEY', 'local-integration-fixture-only');
define('EDIT_DATABASE_PATH', EDIT_BASE_PATH . '/data/database/site.sqlite');
define('EDIT_CORS_ORIGINS', []);
define('EDIT_DEBUG', false);
define('EDIT_SESSION_EXPIRY_HOURS', 24);
define('EDIT_MAX_PAGE_LIMIT', 100);
""")
    (root / 'router.php').write_text("""<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
foreach (['admin-api', 'api'] as $api) {
    if (str_starts_with($path, '/_edit/' . $api . '/')) {
        require __DIR__ . '/_edit/' . $api . '/index.php'; return true;
    }
}
if (!str_starts_with($path, '/_edit/admin/altcha/')) { http_response_code(404); return true; }
return false;
""")
    subprocess.run(['php', str(REPO / 'tests/seed-email-fixture.php'), str(root), str(smtp.server_address[1])], check=True)
    threading.Thread(target=smtp.serve_forever, daemon=True).start()
    port = free_port()
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root), str(root / 'router.php')],
                              env={**os.environ, 'PHP_CLI_SERVER_WORKERS': '4'},
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    base = f'http://127.0.0.1:{port}/_edit'
    database = app / 'data/database/site.sqlite'

    def request(path, data=None, token=None, method=None):
        headers = {'Content-Type': 'application/json'}
        if token:
            headers['Authorization'] = 'Bearer ' + token
        body = data if isinstance(data, bytes) else None if data is None else json.dumps(data).encode()
        req = urllib.request.Request(base + path, data=body, headers=headers, method=method)
        try:
            response = urllib.request.urlopen(req, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            raw = response.read()
            return response.status, json.loads(raw) if response.headers.get_content_type() == 'application/json' else raw, response.headers

    def proof():
        code, challenge, headers = request('/api/captcha')
        assert code == 200 and headers['Cache-Control'] == 'no-store'
        assert 'html' not in challenge and challenge['parameters']['algorithm'] == 'PBKDF2/SHA-256'
        result = subprocess.run(['node', str(REPO / 'tests/solve-altcha.mjs')], input=json.dumps(challenge),
                                capture_output=True, text=True, check=True, timeout=30)
        return result.stdout

    def reset_limits():
        with sqlite3.connect(database) as db:
            db.execute('DELETE FROM rate_limits')

    message = {'subject': 'Fixture inquiry', 'message': 'Hello\n.\nRCPT TO:<injected@example.invalid>\nLast line',
               'reply_to': 'visitor@example.invalid'}
    try:
        for _ in range(100):
            try:
                request('/api/captcha', method='OPTIONS')
                break
            except urllib.error.URLError:
                time.sleep(.05)
        else:
            raise AssertionError('PHP fixture failed to start')

        assert request('/admin/altcha/altcha.min.js')[0] == 200
        assert b'MIT License' in request('/admin/altcha/LICENSE.txt')[1]
        assert request('/admin/altcha/contact-example.html')[0] == 200
        assert request('/api/send-email/token')[0] == 410
        assert request('/admin-api/send-email/token')[0] == 410
        assert request('/api/send-email', message)[0] == 403
        assert request('/admin-api/send-email', {**message, 'token': 'legacy', 'captcha_answer': 'abc'})[0] == 403
        assert request('/api/send-email', {**message, 'altcha': 'broken'})[0] == 403
        assert request('/api/send-email', b'{broken')[0] == 400
        assert request('/api/send-email', {**message, 'subject': ['not a string']})[0] == 400
        assert request('/api/send-email', {**message, 'subject': 'Hello\r\nBcc: injection'})[0] == 400
        assert request('/api/send-email', {**message, 'message': 'x' * 40000})[0] == 413
        assert request('/admin-api/email-test', {**message, 'to': 'test@example.invalid'})[0] == 401
        assert len(messages) == 0

        solved = proof()
        assert request('/api/send-email', {**message, 'altcha': solved, 'to': 'attacker@example.invalid'})[0] == 400
        assert len(messages) == 0
        assert request('/api/send-email', {**message, 'altcha': solved})[0] == 200
        assert len(messages) == 1
        recipients, body = messages[-1]
        assert recipients == ['RCPT TO:<inquiries@example.invalid>']
        assert b'Reply-To: visitor@example.invalid\r\n' in body
        assert b'\r\n\r\nHello\r\n..\r\nRCPT TO:' in body
        assert request('/admin-api/send-email', {**message, 'altcha': solved})[0] == 403
        assert len(messages) == 1

        reset_limits()
        solved = proof()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            statuses = list(pool.map(lambda _: request('/api/send-email', {**message, 'altcha': solved})[0], range(2)))
        assert sorted(statuses) == [200, 403], statuses
        assert len(messages) == 2

        fail_delivery = True
        solved = proof()
        status, failure, _ = request('/api/send-email', {**message, 'altcha': solved})
        assert status == 502 and 'fixture' not in failure['error']
        assert request('/api/send-email', {**message, 'altcha': solved})[0] == 403
        fail_delivery = False

        solved = proof()
        with sqlite3.connect(database) as db:
            db.execute('UPDATE captcha_challenges SET expires_at = 0')
        assert request('/api/send-email', {**message, 'altcha': solved})[0] == 403

        status, login, _ = request('/admin-api/auth/login', {'email': 'admin@example.invalid', 'password': 'FixturePassword789!'})
        assert status == 200
        token = login['token']
        status, settings, _ = request('/admin-api/email-settings', token=token)
        assert status == 200 and settings['captcha_enabled'] is True
        assert settings['contact_recipient'] == 'inquiries@example.invalid'
        settings['captcha_enabled'] = False
        assert request('/admin-api/email-settings', settings, token, 'PUT')[0] == 200
        assert request('/api/send-email', message)[0] == 200
        settings['captcha_enabled'] = True
        assert request('/admin-api/email-settings', settings, token, 'PUT')[0] == 200
        assert request('/api/send-email', message)[0] == 403
        assert request('/admin-api/email-test', {**message, 'to': 'test@example.invalid'}, token)[0] == 200
        assert messages[-1][0] == ['RCPT TO:<test@example.invalid>']

        reset_limits()
        for _ in range(10):
            assert request('/api/captcha')[0] == 200
        assert request('/api/captcha')[0] == 429
        reset_limits()
        for _ in range(20):
            assert request('/api/send-email', message)[0] == 403
        assert request('/api/send-email', message)[0] == 429
        print('PASS: JS/PHP proof interoperability, replay/concurrency, expiry, input limits, recipient enforcement, SMTP framing, admin tests, opt-out and rate limits')
    finally:
        with contextlib.suppress(ProcessLookupError):
            os.killpg(server.pid, signal.SIGTERM)
        server.wait(timeout=5)
        smtp.shutdown()
