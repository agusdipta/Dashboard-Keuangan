<?php
header('Content-Type: text/html; charset=utf-8');
// Hanya halaman dan aset ini yang dapat diakses dari internet.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pages = ['index.php', 'login.php', 'register.php', 'logout.php', 'transaksi.php',
    'tambah.php', 'edit.php', 'hapus.php', 'laporan.php', 'pengaturan.php', 'generate_pdf_html.php'];
$assets = ['styles.css' => 'text/css', 'script.js' => 'text/javascript',
    'scan-struk.js' => 'text/javascript', 'receipt-parser.js' => 'text/javascript',
    'receipt-crop.js' => 'text/javascript'];
$file = $path === '/' ? 'index.php' : substr($path ?: '', 1);
if (isset($assets[$file])) {
    header('Content-Type: ' . $assets[$file] . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile(dirname(__DIR__) . '/' . $file);
} elseif (in_array($file, $pages, true)) {
    header('Cache-Control: private, no-store');
    require dirname(__DIR__) . '/' . $file;
} else {
    http_response_code(404);
    echo 'Halaman tidak ditemukan.';
}
