<?php
$koneksi = new mysqli("127.0.0.1", "root", "", "db_keuangan");

$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];
$jumlah = $_POST['jumlah'];
$tipe = $_POST['tipe'];

$koneksi->query("INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe)
VALUES ('$tanggal', '$keterangan', $jumlah, '$tipe')");

header("Location: index.php");
?>
