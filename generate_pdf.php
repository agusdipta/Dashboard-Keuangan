<?php
// Pastikan folder font ada
if (!is_dir('fpdf/font')) {
    mkdir('fpdf/font', 0777, true);
}

require('fpdf/fpdf.php');

// Koneksi ke database
$koneksi = new mysqli("localhost", "root", "", "db_keuangan");

// Ambil parameter tanggal
$tanggal_mulai = $_POST['tanggal_mulai'];
$tanggal_akhir = $_POST['tanggal_akhir'];

// Ambil saldo awal
$res_saldo = $koneksi->query("SELECT saldo_awal FROM saldo WHERE id = 1");
if($res_saldo->num_rows > 0) {
    $row_saldo = $res_saldo->fetch_assoc();
    $saldo_awal = $row_saldo['saldo_awal'];
} else {
    // Jika tidak ada data, gunakan nilai default
    $saldo_awal = 0;
}

// Query transaksi berdasarkan filter
$sql_transaksi = "SELECT * FROM transaksi WHERE tanggal BETWEEN '$tanggal_mulai' AND '$tanggal_akhir' ORDER BY tanggal";
$result_transaksi = $koneksi->query($sql_transaksi);

// Hitung total pemasukan dan pengeluaran
$total_pemasukan = 0;
$total_pengeluaran = 0;
$data_transaksi = [];

if ($result_transaksi->num_rows > 0) {
    while ($row = $result_transaksi->fetch_assoc()) {
        $data_transaksi[] = $row;
        if ($row['tipe'] === 'pemasukan') {
            $total_pemasukan += $row['jumlah'];
        } else {
            $total_pengeluaran += $row['jumlah'];
        }
    }
}

// Hitung saldo akhir
$saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

// Buat PDF
class PDF extends FPDF
{
    function Header()
    {
        global $tanggal_mulai, $tanggal_akhir;
        
        // Logo (jika ada)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Judul
        $this->SetFont('Courier', 'B', 16);
        $this->Cell(0, 10, 'LAPORAN KEUANGAN', 0, 1, 'C');
        
        // Periode
        $this->SetFont('Courier', '', 12);
        $this->Cell(0, 10, 'Periode: ' . date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir)), 0, 1, 'C');
        
        // Garis
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Courier', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Inisialisasi PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Ringkasan Keuangan
$pdf->SetFont('Courier', 'B', 14);
$pdf->Cell(0, 10, 'Ringkasan Keuangan', 0, 1);

$pdf->SetFont('Courier', '', 12);
$pdf->Cell(60, 10, 'Saldo Awal', 1);
$pdf->Cell(0, 10, 'Rp ' . number_format($saldo_awal, 0, ',', '.'), 1, 1);

$pdf->Cell(60, 10, 'Total Pemasukan', 1);
$pdf->Cell(0, 10, 'Rp ' . number_format($total_pemasukan, 0, ',', '.'), 1, 1);

$pdf->Cell(60, 10, 'Total Pengeluaran', 1);
$pdf->Cell(0, 10, 'Rp ' . number_format($total_pengeluaran, 0, ',', '.'), 1, 1);

$pdf->SetFont('Courier', 'B', 12);
$pdf->Cell(60, 10, 'Saldo Akhir', 1);
$pdf->Cell(0, 10, 'Rp ' . number_format($saldo_akhir, 0, ',', '.'), 1, 1);

$pdf->Ln(10);

// Daftar Transaksi
$pdf->SetFont('Courier', 'B', 14);
$pdf->Cell(0, 10, 'Daftar Transaksi', 0, 1);

// Header tabel
$pdf->SetFont('Courier', 'B', 12);
$pdf->Cell(40, 10, 'Tanggal', 1, 0, 'C');
$pdf->Cell(60, 10, 'Keterangan', 1, 0, 'C');
$pdf->Cell(40, 10, 'Jumlah', 1, 0, 'C');
$pdf->Cell(50, 10, 'Tipe', 1, 1, 'C');

// Isi tabel
$pdf->SetFont('Courier', '', 10);
foreach ($data_transaksi as $row) {
    $pdf->Cell(40, 10, date('d/m/Y', strtotime($row['tanggal'])), 1);
    $pdf->Cell(60, 10, $row['keterangan'], 1);
    $pdf->Cell(40, 10, 'Rp ' . number_format($row['jumlah'], 0, ',', '.'), 1);
    $pdf->Cell(50, 10, ucfirst($row['tipe']), 1, 1);
}

// Output PDF
$pdf->Output('Laporan_Keuangan_' . date('Y-m-d') . '.pdf', 'I');
?>
