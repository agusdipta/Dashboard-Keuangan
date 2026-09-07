"""Run against an EMPTY, disposable PostgreSQL database with schema.sql applied.
FINTRACK_TEST_DATABASE_URL=postgresql://... python3 tests/postgres-smoke.py
"""
import datetime
import http.cookiejar
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
url = os.environ['FINTRACK_TEST_DATABASE_URL']
env = dict(os.environ, DATABASE_URL=url)
ports = []
for _ in range(2):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        ports.append(sock.getsockname()[1])
log = tempfile.TemporaryFile()
servers = [subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', 'api/index.php'],
           cwd=ROOT, env=env, stdout=log, stderr=log) for port in ports]
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, data=None, instance=0, expected=200):
    payload = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    try:
        response = client.open(f'http://127.0.0.1:{ports[instance]}/{path}', payload, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.status == expected, (path, response.status, body[:200])
    assert 'Fatal error' not in body and 'Warning:' not in body, body[:200]
    return body

def csrf(page):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', page)[1]

try:
    for _ in range(50):
        try:
            request('styles.css', instance=0)
            request('styles.css', instance=1)
            break
        except urllib.error.URLError:
            time.sleep(.1)
    for path in ['koneksi.php', 'database.php', '.env', 'db_keuangan.sql', 'vendor/autoload.php']:
        request(path, expected=404)
    page = request('register.php')
    request('register.php', dict(csrf=csrf(page), nama='Smoke Admin', username='smokeadmin',
            password='SmokePassword123!', password2='SmokePassword123!'))
    page = request('login.php')
    page = request('login.php', dict(csrf=csrf(page), username='smokeadmin', password='SmokePassword123!'))
    assert 'Smoke Admin' in page, 'Login failed'
    # Same cookie on another PHP process verifies database-backed session and CSRF.
    page = request('pengaturan.php', instance=1)
    assert 'Smoke Admin' in page
    token = csrf(page)
    request('tambah.php', dict(csrf='bad'), expected=419)
    request('pengaturan.php', dict(csrf=token, update_saldo='', saldo_awal='12500.50'))
    cat = dict(csrf=token, tambah_kategori='', nama='Smoke Category', tipe='pengeluaran', ikon='fa-tag', warna='#123456')
    request('pengaturan.php', cat)
    assert 'sudah ada' in request('pengaturan.php', cat)
    today = datetime.date.today().isoformat()
    page = request('tambah.php', dict(csrf=token, tanggal=today, keterangan="Smoke O'Reilly", jumlah='75000', tipe='pengeluaran', kategori_id=''))
    page = request('transaksi.php')
    assert 'Smoke O&#039;Reilly' in page
    txid = re.search(r'edit.php\?id=(\d+)', page)[1]
    request('edit.php', dict(csrf=token, id=txid, tanggal=today, keterangan='Smoke edited', jumlah='80000', tipe='pengeluaran', kategori_id=''))
    for path in ['index.php', 'transaksi.php', 'laporan.php', 'generate_pdf_html.php']:
        page = request(path, {'csrf': token} if path == 'generate_pdf_html.php' else None, instance=1)
        assert 'Smoke edited' in page or path == 'laporan.php', path
    backup = request('pengaturan.php', dict(csrf=token, backup_db=''))
    assert 'INSERT INTO transaksi' in backup and 'Smoke edited' in backup and 'app_session' not in backup
    # A pending user must be approved and cannot modify another user's data.
    admin_client = client
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    page = request('register.php')
    request('register.php', dict(csrf=csrf(page), nama='Smoke User', username='smokeuser',
            password='SmokePassword123!', password2='SmokePassword123!'))
    page = request('login.php')
    assert 'menunggu persetujuan' in request('login.php', dict(csrf=csrf(page), username='smokeuser', password='SmokePassword123!'))
    user_client = client
    client = admin_client
    page = request('pengaturan.php')
    uid = re.search(r'name="user_id" value="(\d+)"', page)[1]
    request('pengaturan.php', dict(csrf=token, user_id=uid, user_aksi='setujui'))
    client = user_client
    page = request('login.php')
    page = request('login.php', dict(csrf=csrf(page), username='smokeuser', password='SmokePassword123!', ingat='1'))
    user_token = csrf(request('pengaturan.php'))
    assert 'Smoke edited' not in request('transaksi.php')
    request('edit.php?id=' + txid, expected=404)
    request('hapus.php', {'csrf': user_token, 'ids[]': [txid]})
    client = admin_client
    assert 'Smoke edited' in request('transaksi.php')
    request('hapus.php', {'csrf': token, 'ids[]': [txid]})
    assert 'Smoke edited' not in request('transaksi.php')
    request('logout.php')
    assert 'Masuk' in request('index.php', instance=1)
    print('PASS: PostgreSQL registration, login across instances, CSRF, saldo, categories, CRUD, reports, backup, approval, user isolation, logout, private routes')
finally:
    for server in servers:
        server.terminate()
        server.wait(timeout=5)
    log.seek(0)
    output = log.read().decode()
    if 'FinTrack:' in output or 'Fatal error' in output:
        print(output)
    log.close()
