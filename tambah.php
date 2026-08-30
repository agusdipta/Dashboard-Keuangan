<?php
require __DIR__ . '/auth.php';
csrf_check();

$tanggal     = $_POST['tanggal'] ?? '';
$keterangan  = trim($_POST['keterangan'] ?? '');
$jumlah_raw  = preg_replace('/\D/', '', (string) ($_POST['jumlah'] ?? '')); // buang pemisah ribuan
$tipe        = $_POST['tipe'] ?? '';
$kategori_id = $_POST['kategori_id'] ?? '';

$errors = [];
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

if ($errors) {
    http_response_code(422);
    echo '<p>' . implode('<br>', array_map('e', $errors)) . '</p>';
    echo '<p><a href="index.php">&larr; Kembali</a></p>';
    exit;
}

$jumlah = (int) $jumlah_raw;
$stmt = $koneksi->prepare(
    "INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe, kategori_id, user_id) VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('ssisii', $tanggal, $keterangan, $jumlah, $tipe, $kategori_id, $UID);
$stmt->execute();
$stmt->close();

set_flash('Transaksi berhasil ditambahkan.');
header('Location: index.php');
exit;
