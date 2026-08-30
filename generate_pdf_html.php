<?php
require __DIR__ . '/auth.php';
csrf_check();

// Ambil parameter tanggal (validasi format YYYY-MM-DD)
$tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
$tanggal_akhir = $_POST['tanggal_akhir'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai)) {
    $tanggal_mulai = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-t');
}

// Nama pemilik laporan
$nama_user = $USER['nama_lengkap'];

// Saldo awal milik user ini
$saldo_awal = saldo_awal_user($koneksi, $UID);

// Query transaksi berdasarkan filter (prepared statement)
$stmt = $koneksi->prepare(
    "SELECT t.*, k.nama AS kategori_nama
     FROM transaksi t
     LEFT JOIN kategori k ON k.id = t.kategori_id
     WHERE t.user_id = ? AND t.tanggal BETWEEN ? AND ?
     ORDER BY t.tanggal"
);
$stmt->bind_param('iss', $UID, $tanggal_mulai, $tanggal_akhir);
$stmt->execute();
$result_transaksi = $stmt->get_result();

$total_pemasukan = 0;
$total_pengeluaran = 0;
$data_transaksi = [];

while ($row = $result_transaksi->fetch_assoc()) {
    $data_transaksi[] = $row;
    if ($row['tipe'] === 'pemasukan') {
        $total_pemasukan += $row['jumlah'];
    } else {
        $total_pengeluaran += $row['jumlah'];
    }
}
$stmt->close();

$saldo_akhir = $saldo_awal + $total_pemasukan - $total_pengeluaran;

$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f1f4fa;
            margin: 0;
        }
        .container {
            max-width: 850px;
            margin: 30px auto 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(40,60,100,0.09);
            padding: 24px 22px 32px 22px;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
        }
        .header h1 {
            color: #2946a6;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .periode {
            text-align: center;
            color: #6a6a80;
            margin-bottom: 8px;
            font-size: 1.04em;
        }
        .nama-user {
            text-align: center;
            color: #3c5ad6;
            font-weight: 500;
            font-size: 1.07em;
            margin-bottom: 20px;
        }
        h2 {
            color: #3853b2;
            margin-top: 16px;
            margin-bottom: 9px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
            background: #fafdff;
            border-radius: 7px;
            overflow: hidden;
        }
        th {
            background: linear-gradient(to right,#3853b2,#4f72e3);
            color: #fff;
            padding: 10px 7px;
            border: none;
            font-size: 1em;
            letter-spacing: 1px;
        }
        td {
            padding: 8px 7px;
            border-bottom: 1px solid #e5e8f1;
            background: #fff;
            font-size: 0.97em;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr.income {
            background: #eafbe8 !important;
        }
        tr.expense {
            background: #fff2f0 !important;
        }
        .total-row td {
            font-weight: bold;
            background: #f0f6ff !important;
        }
        .income {
            color: #219150;
            font-weight: 600;
        }
        .expense {
            color: #e53935;
            font-weight: 600;
        }
        table tbody tr:hover {
            background: #f0f6ff !important;
            transition: background 0.2s;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.95em;
            color: #93a1bf;
        }
        .print-btn, .pdf-btn {
            background: linear-gradient(90deg,#3853b2,#59b2ff);
            color: white;
            border: none;
            padding: 10px 26px;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            margin-top: 16px;
            margin-right: 8px;
            box-shadow: 0 2px 8px rgba(40,60,100,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .print-btn:hover, .pdf-btn:hover {
            background: linear-gradient(90deg,#59b2ff,#3853b2);
            box-shadow: 0 4px 16px rgba(90,120,200,0.12);
        }
        @media (max-width: 600px) {
            .container {
                padding: 10px 2vw;
            }
            th, td {
                font-size: 0.93em;
                padding: 8px 3px;
            }
        }
        @media print {
            body {
                background: #fff;
            }
            .container {
                box-shadow: none;
                background: #fff;
                padding: 0;
            }
            .print-btn, .pdf-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container" id="laporan-container">
        <div class="header">
            <h1>LAPORAN KEUANGAN</h1>
        </div>
        <div class="periode">
            Periode: ' . date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir)) . '
        </div>
        <div class="nama-user">
            Nama: ' . htmlspecialchars($nama_user) . '
        </div>
        <div class="summary">
            <h2>Ringkasan Keuangan</h2>
            <table>
                <tr>
                    <td width="40%">Saldo Awal</td>
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
            <table id="pdf-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Kategori</th>
                        <th>Jumlah</th>
                        <th>Tipe</th>
                    </tr>
                </thead>
                <tbody>';
    foreach ($data_transaksi as $row) {
        $tipe_class = $row['tipe'] === 'pemasukan' ? 'income' : 'expense';
        $row_class = $row['tipe'] === 'pemasukan' ? 'income' : 'expense';
        $html .= '
                    <tr class="' . $row_class . '">
                        <td style="text-align:center">' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
                        <td>' . htmlspecialchars($row['keterangan']) . '</td>
                        <td>' . htmlspecialchars($row['kategori_nama'] ?? '-') . '</td>
                        <td style="text-align:right">Rp ' . number_format($row['jumlah'], 0, ',', '.') . '</td>
                        <td style="text-align:center" class="' . $tipe_class . '">' . ucfirst($row['tipe']) . '</td>
                    </tr>';
    }
    $html .= '
                </tbody>
            </table>';
} else {
    $html .= '<p style="text-align:center;color:#95a;">Tidak ada transaksi untuk periode ini.</p>';
}

$html .= '
        </div>
        <div class="footer">
            <p>Laporan ini dibuat pada ' . date('d/m/Y H:i:s') . '</p>
        </div>
        <div style="text-align: center;">
            <button class="print-btn" onclick="window.print()">Cetak Laporan</button>
            <button class="pdf-btn" id="download-pdf-btn" type="button">Download PDF</button>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById("download-pdf-btn").addEventListener("click", function() {
            var element = document.getElementById("laporan-container");
            var opt = {
                margin: 0.3,
                filename: "Laporan_Keuangan_' . date('Ymd_His') . '.pdf",
                image: { type: "jpeg", quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: "in", format: "a4", orientation: "portrait" }
            };
            html2pdf().from(element).set(opt).save();
        });
    </script>
</body>
</html>';

echo $html;
?>