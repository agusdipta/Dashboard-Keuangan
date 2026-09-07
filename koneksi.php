<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

/* ================================================================== *
 *  Konstanta
 * ================================================================== */

const KATEGORI_DEFAULT = [
    ['Gaji', 'pemasukan', 'fa-money-check-dollar', '#2e7d32'],
    ['Bonus', 'pemasukan', 'fa-gift', '#43a047'],
    ['Penjualan', 'pemasukan', 'fa-store', '#66bb6a'],
    ['Transfer Masuk', 'pemasukan', 'fa-arrow-down', '#1b5e20'],
    ['Lainnya (Masuk)', 'pemasukan', 'fa-circle-plus', '#81c784'],
    ['Makanan & Minuman', 'pengeluaran', 'fa-utensils', '#ef5350'],
    ['Transportasi', 'pengeluaran', 'fa-car', '#ff7043'],
    ['Belanja', 'pengeluaran', 'fa-bag-shopping', '#ec407a'],
    ['Tagihan & Utilitas', 'pengeluaran', 'fa-file-invoice-dollar', '#ab47bc'],
    ['Kesehatan', 'pengeluaran', 'fa-heart-pulse', '#26a69a'],
    ['Hiburan', 'pengeluaran', 'fa-clapperboard', '#5c6bc0'],
    ['Pendidikan', 'pengeluaran', 'fa-book', '#42a5f5'],
    ['Lainnya (Keluar)', 'pengeluaran', 'fa-circle-minus', '#9e9e9e'],
];

/* ================================================================== *
 *  Helper umum
 * ================================================================== */

/** Escape untuk output HTML (cegah XSS). */
function e($str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/** Token CSRF untuk sesi ini. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Input hidden CSRF untuk ditaruh di dalam <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Validasi token CSRF pada request POST; hentikan eksekusi bila salah. */
function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    $sesi  = $_SESSION['csrf'] ?? '';
    if ($sesi === '' || !is_string($token) || $token === '' || !hash_equals($sesi, $token)) {
        http_response_code(419);
        die('Sesi tidak valid / token CSRF salah. Silakan kembali dan coba lagi.');
    }
}

/** Simpan pesan singkat untuk ditampilkan sekali di halaman berikutnya. */
function set_flash(string $pesan, string $tipe = 'success'): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

/** Ambil & hapus pesan flash (hanya tampil sekali). */
function get_flash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* ================================================================== *
 *  Autentikasi
 * ================================================================== */

/** Buat cookie "ingat saya" (berlaku 30 hari). */
function set_cookie_ingat(PDO $koneksi, int $user_id): void
{
    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $exp       = date('Y-m-d H:i:s', time() + 30 * 24 * 3600);

    $stmt = $koneksi->prepare(
        "INSERT INTO auth_token (user_id, selector, validator_hash, kadaluarsa) VALUES (?, ?, ?, ?)"
    );
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $selector, PDO::PARAM_STR);
    $stmt->bindValue(3, $hash, PDO::PARAM_STR);
    $stmt->bindValue(4, $exp, PDO::PARAM_STR);
    $stmt->execute();
    $stmt->closeCursor();

    setcookie('ingat', $selector . ':' . $validator, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => session_get_cookie_params()['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Periksa cookie "ingat saya"; kembalikan user_id atau null. */
function cek_cookie_ingat(PDO $koneksi): ?int
{
    $raw = $_COOKIE['ingat'] ?? '';
    if (!is_string($raw) || !str_contains($raw, ':')) {
        return null;
    }
    [$selector, $validator] = explode(':', $raw, 2);

    $stmt = $koneksi->prepare(
        "SELECT user_id, validator_hash, kadaluarsa FROM auth_token WHERE selector = ?"
    );
    $stmt->bindValue(1, $selector, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();
    $stmt->closeCursor();

    if (!$row || strtotime($row['kadaluarsa']) < time()) {
        return null;
    }
    if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        return null;
    }
    return (int) $row['user_id'];
}

/** Hapus cookie & token "ingat saya". */
function hapus_cookie_ingat(PDO $koneksi): void
{
    $raw = $_COOKIE['ingat'] ?? '';
    if (is_string($raw) && str_contains($raw, ':')) {
        [$selector] = explode(':', $raw, 2);
        $stmt = $koneksi->prepare("DELETE FROM auth_token WHERE selector = ?");
        $stmt->bindValue(1, $selector, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->closeCursor();
    }
    setcookie('ingat', '', ['expires' => time() - 3600, 'path' => '/']);
}

/** User yang sedang login (atau null). Ikut memeriksa cookie "ingat saya". */
function user_saat_ini(PDO $koneksi): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }

    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        $uid = cek_cookie_ingat($koneksi);
        if ($uid) {
            $_SESSION['uid'] = $uid;
        }
    }
    if (!$uid) {
        return $cache = null;
    }

    $stmt = $koneksi->prepare(
        "SELECT id, username, nama_lengkap, peran, status FROM users WHERE id = ?"
    );
    $stmt->bindValue(1, $uid, PDO::PARAM_INT);
    $stmt->execute();
    $u = $stmt->fetch();
    $stmt->closeCursor();

    if (!$u || $u['status'] !== 'aktif') {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    return $cache = $u;
}

/** Wajib login; kalau belum, alihkan ke halaman login. */
function wajib_login(PDO $koneksi): array
{
    $u = user_saat_ini($koneksi);
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

/** Wajib admin. */
function wajib_admin(PDO $koneksi): array
{
    $u = wajib_login($koneksi);
    if ($u['peran'] !== 'admin') {
        http_response_code(403);
        die('Halaman ini khusus admin. <a href="index.php">Kembali ke Dashboard</a>');
    }
    return $u;
}

/** Salin kategori bawaan untuk seorang user. */
function seed_kategori_untuk_user(PDO $koneksi, int $user_id): void
{
    $stmt = $koneksi->prepare(
        "INSERT INTO kategori (user_id, nama, tipe, ikon, warna) VALUES (?, ?, ?, ?, ?) ON CONFLICT (user_id, nama, tipe) DO NOTHING"
    );
    foreach (KATEGORI_DEFAULT as [$nama, $tipe, $ikon, $warna]) {
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $nama, PDO::PARAM_STR);
        $stmt->bindValue(3, $tipe, PDO::PARAM_STR);
        $stmt->bindValue(4, $ikon, PDO::PARAM_STR);
        $stmt->bindValue(5, $warna, PDO::PARAM_STR);
        $stmt->execute();
    }
    $stmt->closeCursor();
}

/**
 * Baris atas header: chip [inisial · nama · peran] di kiri, tanggal + tombol
 * Keluar (ikon) di kanan. Ditaruh di atas <h1> pada .content-header.
 */
function topbar_user(array $user): string
{
    $nama     = trim($user['nama_lengkap']);
    $inisial  = mb_strtoupper(mb_substr($nama !== '' ? $nama : '?', 0, 1, 'UTF-8'), 'UTF-8');
    $peran    = $user['peran'] === 'admin'
        ? "<span class='user-chip-role'>admin</span>"
        : '';

    return '<div class="header-bar">'
        . '<span class="user-chip">'
        .   '<span class="user-chip-av">' . e($inisial) . '</span>'
        .   '<span class="user-chip-name">' . e($nama) . '</span>'
        .   $peran
        . '</span>'
        . '<span class="header-bar-right">'
        .   '<span class="tgl">' . date('d M Y') . '</span>'
        .   '<button type="button" class="theme-toggle" id="themeToggle" title="Ganti tema terang / gelap" aria-label="Mode gelap">'
        .     '<i class="fas fa-moon"></i>'
        .   '</button>'
        .   '<a href="logout.php" class="logout-btn" title="Keluar" aria-label="Keluar">'
        .     '<i class="fas fa-right-from-bracket"></i>'
        .   '</a>'
        . '</span>'
        . '</div>';
}

/* ================================================================== *
 *  Helper data
 * ================================================================== */

/** Daftar kategori milik satu user (opsional difilter per tipe). */
function ambil_kategori(PDO $koneksi, int $user_id, ?string $tipe = null): array
{
    if ($tipe !== null) {
        $stmt = $koneksi->prepare(
            "SELECT * FROM kategori WHERE user_id = ? AND tipe = ? ORDER BY nama"
        );
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $tipe, PDO::PARAM_STR);
    } else {
        $stmt = $koneksi->prepare(
            "SELECT * FROM kategori WHERE user_id = ? ORDER BY tipe, nama"
        );
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $res = $stmt;
    $data = [];
    while ($row = $res->fetch()) {
        $data[] = $row;
    }
    $stmt->closeCursor();
    return $data;
}

/** Saldo awal milik satu user. */
function saldo_awal_user(PDO $koneksi, int $user_id): float
{
    $stmt = $koneksi->prepare("SELECT saldo_awal FROM saldo WHERE user_id = ?");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row ? (float) $row['saldo_awal'] : 0.0;
}

/** Badge tipe transaksi (pemasukan / pengeluaran). */
function badge_tipe(string $tipe): string
{
    $icon = $tipe === 'pemasukan' ? 'arrow-down' : 'arrow-up';
    $bg   = $tipe === 'pemasukan' ? 'bg-success' : 'bg-danger';
    return "<span class='badge rounded-pill $bg'><i class='fas fa-$icon'></i> " . ucfirst($tipe) . "</span>";
}

/** Chip kategori berwarna; tampilkan "-" bila transaksi tanpa kategori. */
function chip_kategori(?string $nama, ?string $ikon, ?string $warna): string
{
    if (!$nama) {
        return "<span class='text-muted'>-</span>";
    }
    return "<span class='kategori-chip' style='--kat:" . e($warna ?: '#6c757d') . "'>"
        . "<i class='fas " . e($ikon ?: 'fa-tag') . "'></i> " . e($nama) . "</span>";
}

/**
 * Render tabel riwayat transaksi.
 *
 * @param array  $rows    Baris transaksi (hasil JOIN dengan kategori).
 * @param string $kembali Halaman tujuan redirect setelah aksi (index.php / laporan.php).
 * @param bool   $aksi    true = tampilkan checkbox pilih-banyak + tombol Edit/Hapus Terpilih;
 *                        false = tabel hanya-baca (dipakai di Dashboard).
 */
function render_tabel_transaksi(array $rows, string $kembali, bool $aksi = true): void
{
    if (!$rows) {
        echo "<p class='text-center text-muted mb-0'>Tidak ada transaksi untuk periode ini</p>";
        return;
    }

    if ($aksi) {
        echo '<form action="hapus.php" method="post" class="form-hapus-massal">';
        echo csrf_field();
        echo '<input type="hidden" name="kembali" value="' . e($kembali) . '">';
    }
    ?>
    <div class="table-responsive tx-wrap">
        <table class="table table-hover tx-table">
            <thead>
                <tr>
                    <?php if ($aksi): ?><th class="cb-col"><input type="checkbox" class="cb-all" title="Pilih semua" aria-label="Pilih semua"></th><?php endif; ?>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Kategori</th>
                    <th>Jumlah</th>
                    <th>Tipe</th>
                    <?php if ($aksi): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr class="<?= $row['tipe'] === 'pemasukan' ? 'income-row' : 'expense-row' ?>">
                        <?php if ($aksi): ?>
                        <td class="cb-col" data-label="Pilih">
                            <input type="checkbox" class="cb-item" name="ids[]" value="<?= (int) $row['id'] ?>"
                                   aria-label="Pilih transaksi <?= e($row['keterangan']) ?>">
                        </td>
                        <?php endif; ?>
                        <td data-label="Tanggal"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                        <td data-label="Keterangan"><?= e($row['keterangan']) ?></td>
                        <td data-label="Kategori"><?= chip_kategori($row['kategori_nama'] ?? null, $row['kategori_ikon'] ?? null, $row['kategori_warna'] ?? null) ?></td>
                        <td data-label="Jumlah">Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                        <td data-label="Tipe"><?= badge_tipe($row['tipe']) ?></td>
                        <?php if ($aksi): ?>
                        <td class="aksi-cell" data-label="Aksi">
                            <a class="btn btn-sm btn-outline-primary" title="Edit"
                               href="edit.php?id=<?= (int) $row['id'] ?>&kembali=<?= urlencode($kembali) ?>"><i class="fas fa-pen"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    if ($aksi) {
        echo '<div class="hapus-massal-bar">';
        echo '<button type="submit" class="btn btn-sm btn-danger" disabled><i class="fas fa-trash"></i> Hapus Terpilih (<span class="cb-count">0</span>)</button>';
        echo '</div></form>';
    }
}
