<?php
$koneksi = new mysqli("127.0.0.1", "root", "", "db_keuangan");

$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];
$jumlah = $_POST['jumlah'];
$tipe = $_POST['tipe'];

if ($tipe === 'pemasukan') {
    $sql = "INSERT INTO pemasukan (tanggal, keterangan, jumlah) VALUES ('$tanggal', '$keterangan', $jumlah)";
} else {
    $sql = "INSERT INTO pengeluaran (tanggal, keterangan, jumlah) VALUES ('$tanggal', '$keterangan', $jumlah)";
}

if ($koneksi->query($sql)) {
    header("Location: index.php");
} else {
    echo "Gagal menyimpan data: " . $koneksi->error;
}
?>
