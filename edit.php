<?php
require __DIR__ . '/auth.php';

$kembali = $_REQUEST['kembali'] ?? 'index.php';
if (!in_array($kembali, ['index.php', 'laporan.php'], true)) {
    $kembali = 'index.php';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id          = (int) ($_POST['id'] ?? 0);
    $tanggal     = $_POST['tanggal'] ?? '';
    $keterangan  = trim($_POST['keterangan'] ?? '');
    $jumlah_raw  = preg_replace('/\D/', '', (string) ($_POST['jumlah'] ?? '')); // buang pemisah ribuan
    $tipe        = $_POST['tipe'] ?? '';
    $kategori_id = $_POST['kategori_id'] ?? '';

    if ($id <= 0) {
        $errors[] = 'ID transaksi tidak valid.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $errors[] = 'Tanggal tidak valid.';
    }
    if ($keterangan === '') {
        $errors[] = 'Keterangan wajib diisi.';
    }
    if ($jumlah_raw === '' || (int) $jumlah_raw <= 0) {
        $errors[] = 'Jumlah harus berupa angka lebih dari 0.';
    }
    if (!in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $errors[] = 'Tipe transaksi tidak valid.';
    }

    $kategori_id = ($kategori_id !== '') ? (int) $kategori_id : null;
    if ($kategori_id !== null) {
        $stmt = $koneksi->prepare("SELECT tipe FROM kategori WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $kategori_id, $UID);
        $stmt->execute();
        $kat = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$kat) {
            $kategori_id = null;
        } elseif ($kat['tipe'] !== $tipe) {
            $errors[] = 'Kategori tidak sesuai dengan tipe transaksi.';
        }
    }

    // Pastikan transaksi memang milik user ini
    if (!$errors && $id > 0) {
        $cek = $koneksi->prepare("SELECT id FROM transaksi WHERE id = ? AND user_id = ?");
        $cek->bind_param('ii', $id, $UID);
        $cek->execute();
        if (!$cek->get_result()->fetch_assoc()) {
            $cek->close();
            http_response_code(404);
            die('Transaksi tidak ditemukan. <a href="index.php">&larr; Kembali</a>');
        }
        $cek->close();
    }

    if (!$errors) {
        $jumlah = (int) $jumlah_raw;
        $stmt = $koneksi->prepare(
            "UPDATE transaksi SET tanggal = ?, keterangan = ?, jumlah = ?, tipe = ?, kategori_id = ? WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param('ssisiii', $tanggal, $keterangan, $jumlah, $tipe, $kategori_id, $id, $UID);
        $stmt->execute();
        $stmt->close();

        set_flash('Transaksi berhasil diperbarui.');
        header('Location: ' . $kembali);
        exit;
    }

    // Ada error: pertahankan nilai yang sudah diisi user
    $data = [
        'id'          => $id,
        'tanggal'     => $tanggal,
        'keterangan'  => $keterangan,
        'jumlah'      => $jumlah_raw,
        'tipe'        => $tipe,
        'kategori_id' => $kategori_id,
    ];
} else {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $koneksi->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $UID);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        http_response_code(404);
        die('Transaksi tidak ditemukan. <a href="index.php">&larr; Kembali</a>');
    }
}

$daftar_kategori = ambil_kategori($koneksi, $UID);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Edit Transaksi</title>
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
                <li><a href="pengaturan.php"><i class="fas fa-cog"></i> Pengaturan</a></li>
            </ul>
        </nav>

        <main class="content">
            <header class="content-header">
                <?= topbar_user($USER) ?>
                <h1><i class="fas fa-pen"></i> Edit Transaksi</h1>
            </header>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?= implode('<br>', array_map('e', $errors)) ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 640px;">
                <div class="card-header">
                    <h2>Ubah Data Transaksi</h2>
                </div>
                <div class="card-body">
                    <form action="edit.php" method="post" class="transaction-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $data['id'] ?>">
                        <input type="hidden" name="kembali" value="<?= e($kembali) ?>">

                        <div class="form-group">
                            <label for="tanggal"><i class="fas fa-calendar"></i> Tanggal</label>
                            <input type="date" id="tanggal" name="tanggal" class="form-control"
                                   value="<?= e($data['tanggal']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="keterangan"><i class="fas fa-file-alt"></i> Keterangan</label>
                            <input type="text" id="keterangan" name="keterangan" class="form-control"
                                   value="<?= e($data['keterangan']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="jumlah"><i class="fas fa-money-bill"></i> Jumlah (Rp)</label>
                            <input type="text" inputmode="numeric" id="jumlah" name="jumlah" class="form-control js-rupiah"
                                   value="<?= (int) $data['jumlah'] ?>" autocomplete="off" required>
                        </div>
                        <div class="form-group">
                            <label for="tipe"><i class="fas fa-exchange-alt"></i> Tipe Transaksi</label>
                            <select id="tipe" name="tipe" class="form-control" required>
                                <option value="pemasukan" <?= $data['tipe'] === 'pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                                <option value="pengeluaran" <?= $data['tipe'] === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kategori_id"><i class="fas fa-tags"></i> Kategori</label>
                            <select id="kategori_id" name="kategori_id" class="form-control">
                                <option value="">— Tanpa kategori —</option>
                                <?php foreach ($daftar_kategori as $k): ?>
                                    <option value="<?= (int) $k['id'] ?>" data-tipe="<?= e($k['tipe']) ?>"
                                        <?= (int) ($data['kategori_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>>
                                        <?= e($k['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="btn-block">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="<?= e($kembali) ?>" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Saring pilihan kategori sesuai tipe transaksi
        (function () {
            const tipe = document.getElementById('tipe');
            const kategori = document.getElementById('kategori_id');
            if (!tipe || !kategori) return;
            const semua = Array.from(kategori.options)
                .filter(o => o.value !== '')
                .map(o => ({ value: o.value, text: o.textContent.trim(), tipe: o.dataset.tipe }));
            const terpilihAwal = kategori.value;
            function sync() {
                const t = tipe.value;
                const sebelumnya = kategori.value;
                kategori.innerHTML = '<option value="">— Tanpa kategori —</option>';
                semua.filter(o => o.tipe === t).forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.value;
                    opt.textContent = o.text;
                    if (o.value === sebelumnya || o.value === terpilihAwal) opt.selected = true;
                    kategori.appendChild(opt);
                });
            }
            tipe.addEventListener('change', sync);
            sync();
        })();
    </script>
    <?php require __DIR__ . '/partial_mobilenav.php'; ?>
</body>
</html>
