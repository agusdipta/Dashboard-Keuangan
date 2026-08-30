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
             ON DUPLICATE KEY UPDATE saldo_awal = VALUES(saldo_awal)"
        );
        $stmt->bind_param('id', $UID, $saldo_baru);
        if ($stmt->execute()) {
            $pesan = "<div class='alert alert-success'>Saldo awal berhasil diperbarui!</div>";
            $saldo_awal = $saldo_baru;
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal memperbarui saldo awal: " . e($koneksi->error) . "</div>";
        }
        $stmt->close();
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
            "INSERT INTO kategori (user_id, nama, tipe, ikon, warna) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $UID, $nama, $tipe, $ikon, $warna);
        if ($stmt->execute()) {
            $pesan = "<div class='alert alert-success'>Kategori \"" . e($nama) . "\" ditambahkan.</div>";
        } elseif ($koneksi->errno === 1062) {
            $pesan = "<div class='alert alert-warning'>Kategori \"" . e($nama) . "\" untuk tipe tersebut sudah ada.</div>";
        } else {
            $pesan = "<div class='alert alert-danger'>Gagal menambah kategori: " . e($koneksi->error) . "</div>";
        }
        $stmt->close();
    }
}

/* ---- Hapus kategori (milik user ini; transaksi terkait jadi tanpa kategori) ---- */
if (isset($_POST['hapus_kategori'])) {
    $kat_id = (int) ($_POST['kategori_id'] ?? 0);
    if ($kat_id > 0) {
        $stmt = $koneksi->prepare(
            "UPDATE transaksi SET kategori_id = NULL WHERE kategori_id = ? AND user_id = ?"
        );
        $stmt->bind_param('ii', $kat_id, $UID);
        $stmt->execute();
        $stmt->close();

        $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $kat_id, $UID);
        $stmt->execute();
        $stmt->close();

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
        $stmt->bind_param('si', $status_baru, $target);
        $stmt->execute();
        $stmt->close();

        if ($aksi === 'setujui') {
            $s = $koneksi->prepare("INSERT IGNORE INTO saldo (user_id, saldo_awal) VALUES (?, 0)");
            $s->bind_param('i', $target);
            $s->execute();
            $s->close();
            $s = $koneksi->prepare("SELECT COUNT(*) AS n FROM kategori WHERE user_id = ?");
            $s->bind_param('i', $target);
            $s->execute();
            $n = (int) $s->get_result()->fetch_assoc()['n'];
            $s->close();
            if ($n === 0) {
                seed_kategori_untuk_user($koneksi, $target);
            }
        }
        $pesan = "<div class='alert alert-success'>Status user diperbarui.</div>";
    } elseif ($target === $UID) {
        $pesan = "<div class='alert alert-warning'>Kamu tidak bisa mengubah statusmu sendiri.</div>";
    }
}

/* ---- Backup database (admin, disimpan di luar folder web) ---- */
if ($is_admin && isset($_POST['backup_db'])) {
    $backup_dir = __DIR__ . '/../../backup_keuangan';
    if (!is_dir($backup_dir)) {
        @mkdir($backup_dir, 0775, true);
    }
    if (is_dir($backup_dir) && !file_exists($backup_dir . '/.htaccess')) {
        @file_put_contents($backup_dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }

    if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
        $pesan = "<div class='alert alert-danger'>Folder backup tidak bisa dibuat/ditulis: " . e($backup_dir) . "</div>";
    } else {
        $tables = [];
        $result = $koneksi->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }

        $nama_file = 'backup_' . date("Y-m-d_H-i-s") . '.sql';
        $handle = fopen($backup_dir . '/' . $nama_file, 'w');

        foreach ($tables as $table) {
            $result = $koneksi->query("SELECT * FROM `$table`");
            $num_fields = $result->field_count;

            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            $row2 = $koneksi->query("SHOW CREATE TABLE `$table`")->fetch_row();
            fwrite($handle, $row2[1] . ";\n\n");

            while ($row = $result->fetch_row()) {
                fwrite($handle, "INSERT INTO `$table` VALUES(");
                for ($j = 0; $j < $num_fields; $j++) {
                    if (isset($row[$j])) {
                        fwrite($handle, "'" . $koneksi->real_escape_string($row[$j]) . "'");
                    } else {
                        fwrite($handle, "NULL");
                    }
                    if ($j < ($num_fields - 1)) {
                        fwrite($handle, ',');
                    }
                }
                fwrite($handle, ");\n");
            }
            fwrite($handle, "\n\n");
        }

        fclose($handle);
        $pesan = "<div class='alert alert-success'>Backup database berhasil dibuat: <code>" . e($nama_file)
            . "</code> (disimpan di folder <code>backup_keuangan</code> di luar folder web).</div>";
    }
}

$daftar_kategori = ambil_kategori($koneksi, $UID);

$daftar_user = [];
if ($is_admin) {
    $r = $koneksi->query(
        "SELECT id, username, nama_lengkap, peran, status, created_at
         FROM users
         ORDER BY FIELD(status,'pending','aktif','nonaktif'), id"
    );
    while ($row = $r->fetch_assoc()) {
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
                            <p>Backup seluruh database (semua user). File disimpan di folder <code>backup_keuangan</code> di luar folder web.</p>
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
