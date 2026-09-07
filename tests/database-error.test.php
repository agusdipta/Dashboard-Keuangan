<?php
require __DIR__ . '/../database_error.php';
$cases = [
    'could not find driver' => 'DB_DRIVER_MISSING',
    'password authentication failed for user secret-user' => 'DB_AUTH_FAILED',
    'no password supplied' => 'DB_PASSWORD_MISSING',
    'could not translate host name private-host' => 'DB_HOST_UNRESOLVED',
    'connection refused' => 'DB_CONNECTION_REFUSED',
    'connection timed out' => 'DB_CONNECTION_TIMEOUT',
    'timeout expired' => 'DB_CONNECTION_TIMEOUT',
    'SSL error: certificate verify failed' => 'DB_TLS_ERROR',
    'endpoint is disabled' => 'DB_ENDPOINT_DISABLED',
    'database private-db does not exist' => 'DB_OBJECT_MISSING',
    'unexpected secret-password postgresql://secret-user:secret-password@host/db' => 'DB_ERROR_UNCLASSIFIED',
];
foreach ($cases as $message => $expected) {
    if (database_error_label(new PDOException($message)) !== $expected) {
        throw new RuntimeException('Incorrect safe database error label: ' . $expected);
    }
}
echo "PASS: database errors classified without exposing raw messages.\n";
