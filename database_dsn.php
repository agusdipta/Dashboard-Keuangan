<?php
function postgres_dsn(string $host, int $port, string $database, string $sslmode): string
{
    $dsn = 'pgsql:host=' . $host . ';port=' . $port
        . ';dbname=' . $database . ';sslmode=' . $sslmode . ';connect_timeout=10';
    // libpq pada Vercel belum tentu mendukung SNI. Suffix -pooler harus tetap ada.
    if (preg_match('/\A(ep-[a-z0-9-]+)\.[a-z0-9.-]+\.neon\.tech\z/i', $host, $match)) {
        $dsn .= ';options=endpoint=' . strtolower($match[1]);
    }
    return $dsn;
}
