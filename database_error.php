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
