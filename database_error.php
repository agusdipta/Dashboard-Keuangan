<?php
/** Hanya keluarkan label tetap; pesan PDO dapat mengandung kredensial. */
function database_error_label(Throwable $error): string
{
    $message = strtolower($error->getMessage());
    foreach ([
        'could not find driver' => 'DB_DRIVER_MISSING',
        'password authentication failed' => 'DB_AUTH_FAILED',
        'no password supplied' => 'DB_PASSWORD_MISSING',
        'could not translate host name' => 'DB_HOST_UNRESOLVED',
        'connection refused' => 'DB_CONNECTION_REFUSED',
        'timeout' => 'DB_CONNECTION_TIMEOUT',
        'timed out' => 'DB_CONNECTION_TIMEOUT',
        'endpoint is disabled' => 'DB_ENDPOINT_DISABLED',
        'does not exist' => 'DB_OBJECT_MISSING',
        'ssl' => 'DB_TLS_ERROR',
        'certificate' => 'DB_TLS_ERROR',
    ] as $fragment => $label) {
        if (str_contains($message, $fragment)) {
            return $label;
        }
    }
    return 'DB_ERROR_UNCLASSIFIED';
}

/** Hanya untuk kegagalan membuka koneksi, bukan error query berisi data user. */
function database_connection_detail(Throwable $error, string $connection_url): string
{
    $parts = parse_url($connection_url);
    if (!$parts || empty($parts['host'])) {
        return 'Connection URL could not be parsed; details omitted.';
    }
    $secrets = [$connection_url];
    foreach (['user', 'pass', 'host', 'path', 'query'] as $key) {
        $value = $parts[$key] ?? '';
        if ($key === 'path') {
            $value = ltrim($value, '/');
        }
        if ($value !== '') {
            $secrets[] = $value;
            $secrets[] = rawurldecode($value);
        }
    }
    usort($secrets, fn($a, $b) => strlen($b) <=> strlen($a));
    $message = str_ireplace($secrets, '[redacted]', $error->getMessage());
    $message = preg_replace('~postgres(?:ql)?://[^\s]+|npg_[A-Za-z0-9]+~i', '[redacted]', $message);
    return substr(preg_replace('/[\x00-\x20\x7f]+/', ' ', $message), 0, 1000);
}
