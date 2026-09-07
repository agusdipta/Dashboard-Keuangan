<?php
/** PostgreSQL lokal atau Neon; rahasia hanya dibaca dari environment. */
date_default_timezone_set('Asia/Makassar');
require_once __DIR__ . '/database_error.php';
require_once __DIR__ . '/database_dsn.php';

set_exception_handler(function (Throwable $error): void {
    global $koneksi;
    if ($koneksi instanceof PDO && $koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    // Jangan menulis connection string atau nilai query ke respons/log publik.
    $label = $error instanceof PDOException ? ' [' . database_error_label($error) . ']' : '';
    error_log('FinTrack: ' . get_class($error) . $label . ' at ' . basename($error->getFile()) . ':' . $error->getLine());
    if ($error instanceof PDOException && !($koneksi instanceof PDO)) {
        error_log('FinTrack connection detail: ' . database_connection_detail($error, getenv('DATABASE_URL') ?: ''));
    }
    http_response_code(500);
    echo 'Layanan sementara tidak tersedia. Silakan coba lagi.';
});

$url = parse_url(getenv('DATABASE_URL') ?: '');
if (!$url || !in_array($url['scheme'] ?? '', ['postgres', 'postgresql'], true)
    || empty($url['host']) || empty($url['path']) || empty($url['user'])) {
    throw new RuntimeException('DATABASE_URL belum dikonfigurasi.');
}
parse_str($url['query'] ?? '', $options);
$host = $url['host'];
$dbname = rawurldecode(ltrim($url['path'], '/'));
$sslmode = $options['sslmode'] ?? 'require';
if (strpbrk($host . $dbname, ";\r\n") !== false
    || !in_array($sslmode, ['disable', 'require', 'verify-ca', 'verify-full'], true)
    || (getenv('VERCEL') && $sslmode === 'disable')) {
    throw new RuntimeException('Konfigurasi database tidak valid.');
}
$koneksi = new PDO(
    postgres_dsn($host, $url['port'] ?? 5432, $dbname, $sslmode),
    rawurldecode($url['user']),
    rawurldecode($url['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false]
);
$koneksi->exec("SET TIME ZONE 'Asia/Makassar'");
