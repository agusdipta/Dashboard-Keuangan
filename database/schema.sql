BEGIN;
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    peran VARCHAR(10) NOT NULL DEFAULT 'user' CHECK (peran IN ('admin','user')),
    status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('aktif','pending','nonaktif')),
    gagal_login INTEGER NOT NULL DEFAULT 0,
    kunci_sampai TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS auth_token (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    selector CHAR(32) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,
    kadaluarsa TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_auth_user ON auth_token(user_id);
CREATE TABLE IF NOT EXISTS saldo (
    id SERIAL PRIMARY KEY,
    saldo_awal NUMERIC(15,2) NOT NULL DEFAULT 0,
    user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS kategori (
    id SERIAL PRIMARY KEY,
    nama VARCHAR(60) NOT NULL,
    tipe VARCHAR(12) NOT NULL CHECK (tipe IN ('pemasukan','pengeluaran')),
    ikon VARCHAR(40) NOT NULL DEFAULT 'fa-tag',
    warna VARCHAR(9) NOT NULL DEFAULT '#6c757d',
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, nama, tipe)
);
CREATE TABLE IF NOT EXISTS transaksi (
    id SERIAL PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan VARCHAR(255) NOT NULL,
    jumlah NUMERIC(15,2) NOT NULL,
    tipe VARCHAR(12) NOT NULL CHECK (tipe IN ('pemasukan','pengeluaran')),
    kategori_id INTEGER REFERENCES kategori(id) ON DELETE SET NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_transaksi_user_tanggal ON transaksi(user_id, tanggal);
CREATE INDEX IF NOT EXISTS idx_transaksi_kategori ON transaksi(kategori_id);
CREATE TABLE IF NOT EXISTS app_session (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_session_expiry ON app_session(expires_at);
COMMIT;
