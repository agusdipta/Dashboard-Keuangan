<?php
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

// Buat HTML untuk laporan
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #4361ee;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .summary {
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .income {
            color: #4caf50;
        }
        .expense {
            color: #f44336;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8em;
            color: #777;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN KEUANGAN</h1>
        <p>Periode: ' . date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir)) . '</p>
    </div>
    
    <div class="summary">
        <h2>Ringkasan Keuangan</h2>
        <table>
            <tr>
                <td width="30%">Saldo Awal</td>
                <td>Rp ' . number_format($saldo_awal, 0, ',', '.') . '</td>
            </tr>
            <tr>
                <td>Total Pemasukan</td>
                <td class="income">Rp ' . number_format($total_pemasukan, 0, ',', '.') . '</td>
            </tr>
            <tr>
                <td>Total Pengeluaran</td>
                <td class="expense">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td>
            </tr>
            <tr class="total-row">
                <td>Saldo Akhir</td>
                <td>Rp ' . number_format($saldo_akhir, 0, ',', '.') . '</td>
            </tr>
        </table>
    </div>
    
    <div class="transactions">
        <h2>Daftar Transaksi</h2>';

if (count($data_transaksi) > 0) {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Jumlah</th>
                    <th>Tipe</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($data_transaksi as $row) {
        $tipe_class = $row['tipe'] === 'pemasukan' ? 'income' : 'expense';
        $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                    <td>' . $row['keterangan'] . '</td>
                    <td>Rp ' . number_format($row['jumlah'], 0, ',', '.') . '</td>
                    <td class="' . $tipe_class . '">' . ucfirst($row['tipe']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
} else {
    $html .= '<p>Tidak ada transaksi untuk periode ini.</p>';
}

$html .= '
    </div>
    
    <div class="footer">
        <p>Laporan ini dibuat pada ' . date('d/m/Y H:i:s') . '</p>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()">Cetak Laporan</button>
    </div>
</body>
</html>';

// Output HTML
echo $html;
?>
