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

$url = 'postgresql://private-user:private%40password@private-host/private-db?sslmode=require';
$message = "SQLSTATE[08006] [7] authentication failed\nprivate-user private@password private%40password private-host private-db $url";
$detail = database_connection_detail(new PDOException($message), $url);
foreach (['private', 'postgresql://', "\n"] as $secret) {
    if (str_contains($detail, $secret)) {
        throw new RuntimeException('Connection diagnostic leaked a secret or newline.');
    }
}
if (!str_contains($detail, 'SQLSTATE[08006] [7] authentication failed')) {
    throw new RuntimeException('Connection diagnostic lost the error reason.');
}
if (database_connection_detail(new PDOException('private-password'), '') !== 'Connection URL could not be parsed; details omitted.') {
    throw new RuntimeException('Malformed URL must not expose error details.');
}
echo "PASS: connection details retain the reason and redact credentials.\n";
