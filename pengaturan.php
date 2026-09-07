<?php
require __DIR__ . '/auth.php';
$AKTIF = 'pengaturan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
}

$is_admin = $USER['peran'] === 'admin';
$saldo_awal = saldo_awal_user($koneksi, $UID);
$pesan = "";

/* ---- Update saldo awal (per user) ---- */
if (isset($_POST['update_saldo'])) {
    $saldo_baru = $_POST['saldo_awal'] ?? '';
    if (!is_numeric($saldo_baru)) {
        $pesan = "<div class='alert alert-danger'>Saldo awal harus berupa angka.</div>";
    } else {
        $saldo_baru = (float) $saldo_baru;
        $stmt = $koneksi->prepare(
            "INSERT INTO saldo (user_id, saldo_awal) VALUES (?, ?)
             ON CONFLICT (user_id) DO UPDATE SET saldo_awal = EXCLUDED.saldo_awal"
        );
        $stmt->bindValue(1, $UID, PDO::PARAM_INT);
        $stmt->bindValue(2, $saldo_baru, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $pesan = "<div class='alert alert-success'>Saldo awal berhasil diperbarui!</div>";
            $saldo_awal = $saldo_baru;
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal memperbarui saldo awal: " . 'Silakan coba lagi.' . "</div>";
        }
        $stmt->closeCursor();
    }
}

/* ---- Tambah kategori (milik user ini) ---- */
if (isset($_POST['tambah_kategori'])) {
    $nama  = trim($_POST['nama'] ?? '');
    $tipe  = $_POST['tipe'] ?? '';
    $ikon  = trim($_POST['ikon'] ?? '');
    $warna = trim($_POST['warna'] ?? '');

    if ($ikon === '' || !preg_match('/^fa-[a-z0-9-]+$/', $ikon)) {
        $ikon = 'fa-tag';
    }
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $warna)) {
        $warna = '#6c757d';
    }

    if ($nama === '' || !in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $pesan = "<div class='alert alert-danger'>Nama dan tipe kategori wajib diisi dengan benar.</div>";
    } else {
        $stmt = $koneksi->prepare(
            "INSERT INTO kategori (user_id, nama, tipe, ikon, warna) VALUES (?, ?, ?, ?, ?) ON CONFLICT (user_id, nama, tipe) DO NOTHING RETURNING id"
        );
        $stmt->bindValue(1, $UID, PDO::PARAM_INT);
        $stmt->bindValue(2, $nama, PDO::PARAM_STR);
        $stmt->bindValue(3, $tipe, PDO::PARAM_STR);
        $stmt->bindValue(4, $ikon, PDO::PARAM_STR);
        $stmt->bindValue(5, $warna, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetchColumn() !== false) {
            $pesan = "<div class='alert alert-success'>Kategori \"" . e($nama) . "\" ditambahkan.</div>";
        } else {
            $pesan = "<div class='alert alert-warning'>Kategori \"" . e($nama) . "\" untuk tipe tersebut sudah ada.</div>";
        }
        $stmt->closeCursor();
    }
}

/* ---- Hapus kategori (milik user ini; transaksi terkait jadi tanpa kategori) ---- */
if (isset($_POST['hapus_kategori'])) {
    $kat_id = (int) ($_POST['kategori_id'] ?? 0);
    if ($kat_id > 0) {
        $stmt = $koneksi->prepare(
            "UPDATE transaksi SET kategori_id = NULL WHERE kategori_id = ? AND user_id = ?"
        );
        $stmt->bindValue(1, $kat_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $UID, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id = ? AND user_id = ?");
        $stmt->bindValue(1, $kat_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $UID, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        $pesan = "<div class='alert alert-success'>Kategori dihapus. Transaksi terkait kini tanpa kategori.</div>";
    }
}

/* ---- Admin: kelola user ---- */
if ($is_admin && isset($_POST['user_aksi'])) {
    $target = (int) ($_POST['user_id'] ?? 0);
    $aksi   = $_POST['user_aksi'];
    $map    = ['setujui' => 'aktif', 'nonaktifkan' => 'nonaktif', 'aktifkan' => 'aktif'];

    if ($target > 0 && $target !== $UID && isset($map[$aksi])) {
        $status_baru = $map[$aksi];
        $stmt = $koneksi->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bindValue(1, $status_baru, PDO::PARAM_STR);
        $stmt->bindValue(2, $target, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        if ($aksi === 'setujui') {
            $s = $koneksi->prepare("INSERT INTO saldo (user_id, saldo_awal) VALUES (?, 0) ON CONFLICT (user_id) DO NOTHING");
            $s->bindValue(1, $target, PDO::PARAM_INT);
            $s->execute();
            $s->closeCursor();
            $s = $koneksi->prepare("SELECT COUNT(*) AS n FROM kategori WHERE user_id = ?");
            $s->bindValue(1, $target, PDO::PARAM_INT);
            $s->execute();
            $n = (int) $s->fetch()['n'];
            $s->closeCursor();
            if ($n === 0) {
                seed_kategori_untuk_user($koneksi, $target);
            }
        }
        $pesan = "<div class='alert alert-success'>Status user diperbarui.</div>";
    } elseif ($target === $UID) {
        $pesan = "<div class='alert alert-warning'>Kamu tidak bisa mengubah statusmu sendiri.</div>";
    }
}

/* ---- Backup data PostgreSQL: unduh langsung, tanpa menulis ke disk server. ---- */
if ($is_admin && isset($_POST['backup_db'])) {
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
    echo "-- Pulihkan pada database kosong yang sudah memakai database/schema.sql.\nBEGIN;\n";
    foreach (['users', 'saldo', 'kategori', 'transaksi'] as $table) {
        $rows = $koneksi->query('SELECT * FROM ' . $table . ' ORDER BY id');
        while ($row = $rows->fetch()) {
            $columns = implode(', ', array_keys($row));
            $values = array_map(fn($v) => $v === null ? 'NULL' : $koneksi->quote((string) $v), array_values($row));
            echo 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . implode(', ', $values) . ");\n";
        }
        echo "SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM $table;\n";
    }
    echo "COMMIT;\n";
    exit;
}

$daftar_kategori = ambil_kategori($koneksi, $UID);

$daftar_user = [];
if ($is_admin) {
    $r = $koneksi->query(
        "SELECT id, username, nama_lengkap, peran, status, created_at
         FROM users
         ORDER BY CASE status WHEN 'pending' THEN 1 WHEN 'aktif' THEN 2 ELSE 3 END, id"
    );
    while ($row = $r->fetch()) {
        $daftar_user[] = $row;
    }
}
$jml_pending = count(array_filter($daftar_user, fn($u) => $u['status'] === 'pending'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pengaturan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <script>(function(){try{var t=localStorage.getItem("appTheme");if(!t)t=window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";document.documentElement.setAttribute("data-theme",t);}catch(e){}})();</script>
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-wallet"></i> FinTrack</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="transaksi.php"><i class="fas fa-receipt"></i> Transaksi</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                <li class="active"><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <?= topbar_user($USER) ?>
                <h1><i class="fas fa-cog"></i> Pengaturan</h1>
            </header>

            <?= $pesan ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Pengaturan Saldo</h2>
                        </div>
                        <div class="card-body">
                            <form action="pengaturan.php" method="post">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <label for="saldo_awal" class="form-label">Saldo Awal</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" step="0.01" class="form-control" id="saldo_awal" name="saldo_awal" value="<?= e($saldo_awal) ?>" required>
                                    </div>
                                    <div class="form-text">Saldo awal dipakai untuk perhitungan saldo akhir kamu.</div>
                                </div>
                                <button type="submit" name="update_saldo" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($is_admin): ?>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Backup Database</h2>
                            <span class="card-sub">admin</span>
                        </div>
                        <div class="card-body">
                            <p>Unduh backup data semua user dalam format SQL PostgreSQL. Sesi login tidak disertakan. Simpan file ini di tempat aman.</p>
                            <form action="pengaturan.php" method="post">
                                <?= csrf_field() ?>
                                <button type="submit" name="backup_db" class="btn btn-success">
                                    <i class="fas fa-database"></i> Backup Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Kelola User</h2>
                            <span class="card-sub">
                                admin<?= $jml_pending > 0 ? " · <strong>$jml_pending menunggu</strong>" : '' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Username</th>
                                            <th>Peran</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daftar_user as $u): ?>
                                            <?php
                                            $badge_status = [
                                                'aktif'    => 'bg-success',
                                                'pending'  => 'bg-warning',
                                                'nonaktif' => 'bg-secondary',
                                            ][$u['status']];
                                            ?>
                                            <tr>
                                                <td><?= e($u['nama_lengkap']) ?><?= $u['id'] === $UID ? " <span class='text-muted'>(kamu)</span>" : '' ?></td>
                                                <td><?= e($u['username']) ?></td>
                                                <td><span class="badge rounded-pill <?= $u['peran'] === 'admin' ? 'bg-primary' : 'bg-light text-dark' ?>"><?= e($u['peran']) ?></span></td>
                                                <td><span class="badge rounded-pill <?= $badge_status ?>"><?= e($u['status']) ?></span></td>
                                                <td class="aksi-cell">
                                                    <?php if ($u['id'] === $UID): ?>
                                                        <span class="text-muted">—</span>
                                                    <?php else: ?>
                                                        <?php if ($u['status'] === 'pending'): ?>
                                                            <form action="pengaturan.php" method="post" class="d-inline">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                                <button name="user_aksi" value="setujui" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Setujui</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($u['status'] !== 'nonaktif'): ?>
                                                            <form action="pengaturan.php" method="post" class="d-inline"
                                                                  onsubmit="return confirm('Nonaktifkan akun ini?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                                <button name="user_aksi" value="nonaktifkan" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i> Nonaktifkan</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form action="pengaturan.php" method="post" class="d-inline">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                                <button name="user_aksi" value="aktifkan" class="btn btn-sm btn-outline-success"><i class="fas fa-rotate-left"></i> Aktifkan</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Kelola Kategori</h2>
                        </div>
                        <div class="card-body">
                            <form action="pengaturan.php" method="post" class="row g-3 align-items-end mb-4">
                                <?= csrf_field() ?>
                                <div class="col-md-4">
                                    <label for="nama" class="form-label">Nama Kategori</label>
                                    <input type="text" class="form-control" id="nama" name="nama" placeholder="mis. Belanja Bulanan" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="tipe" class="form-label">Tipe</label>
                                    <select class="form-control" id="tipe" name="tipe" required>
                                        <option value="pengeluaran">Pengeluaran</option>
                                        <option value="pemasukan">Pemasukan</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="ikon" class="form-label">Ikon (Font Awesome)</label>
                                    <input type="text" class="form-control" id="ikon" name="ikon" placeholder="fa-bag-shopping" value="fa-tag">
                                </div>
                                <div class="col-md-2">
                                    <label for="warna" class="form-label">Warna</label>
                                    <input type="color" class="form-control form-control-color" id="warna" name="warna" value="#6c757d">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="tambah_kategori" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Tambah Kategori
                                    </button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Tipe</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($daftar_kategori) > 0): ?>
                                            <?php foreach ($daftar_kategori as $k): ?>
                                                <tr>
                                                    <td><?= chip_kategori($k['nama'], $k['ikon'], $k['warna']) ?></td>
                                                    <td><?= badge_tipe($k['tipe']) ?></td>
                                                    <td>
                                                        <form action="pengaturan.php" method="post" class="d-inline"
                                                              onsubmit="return confirm('Hapus kategori ini? Transaksi terkait akan menjadi tanpa kategori.');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="kategori_id" value="<?= (int) $k['id'] ?>">
                                                            <button type="submit" name="hapus_kategori" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i> Hapus
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center">Belum ada kategori.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2>Tema Aplikasi</h2>
                        </div>
                        <div class="card-body">
                            <p class="form-text mb-3">Bisa juga diganti cepat lewat tombol <i class="fas fa-moon"></i> di pojok kanan atas.</p>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="theme-option" data-theme="light">
                                    <div class="theme-preview" style="background: linear-gradient(to bottom, #ffffff, #eef1f8); border: 1px solid #e6e9f2;"></div>
                                    <span>Terang</span>
                                </div>
                                <div class="theme-option" data-theme="dark">
                                    <div class="theme-preview" style="background: linear-gradient(to bottom, #1e2432, #0f1420);"></div>
                                    <span>Gelap</span>
                                </div>
                                <div class="theme-option" data-theme="system">
                                    <div class="theme-preview" style="background: linear-gradient(120deg, #ffffff 50%, #0f1420 50%);"></div>
                                    <span>Ikuti Sistem</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Pilih tema — langsung diterapkan & disimpan
        (function () {
            const opsi = document.querySelectorAll('.theme-option');
            const cocokSistem = () =>
                window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const tersimpan = localStorage.getItem('appTheme');
            let pilihan = tersimpan === 'dark' || tersimpan === 'light' ? tersimpan : 'system';

            const tandai = () => {
                opsi.forEach(o => o.classList.toggle('active', o.dataset.theme === pilihan));
            };
            tandai();

            opsi.forEach(o => {
                o.addEventListener('click', () => {
                    pilihan = o.dataset.theme;
                    tandai();
                    if (pilihan === 'system') {
                        localStorage.removeItem('appTheme');
                        document.documentElement.setAttribute('data-theme', cocokSistem());
                    } else {
                        localStorage.setItem('appTheme', pilihan);
                        document.documentElement.setAttribute('data-theme', pilihan);
                    }
                    // segarkan ikon tombol di header
                    const ic = document.querySelector('#themeToggle i');
                    if (ic) {
                        ic.className =
                            document.documentElement.getAttribute('data-theme') === 'dark'
                                ? 'fas fa-sun'
                                : 'fas fa-moon';
                    }
                });
            });
        })();
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
