<?php
require __DIR__ . '/../database_dsn.php';
foreach ([
    'ep-test-example-pooler.c-12.us-east-1.aws.neon.tech' => 'ep-test-example-pooler',
    'ep-test-example.us-east-2.aws.neon.tech' => 'ep-test-example',
    'localhost' => null,
    'ep-test-example.aws.neon.tech.example.com' => null,
] as $hostname => $endpoint) {
    $expected = 'pgsql:host=' . $hostname . ';port=5432;dbname=testdb;sslmode=require;connect_timeout=10';
    if ($endpoint !== null) {
        $expected .= ';options=endpoint=' . $endpoint;
    }
    if (postgres_dsn($hostname, 5432, 'testdb', 'require') !== $expected) {
        throw new RuntimeException('Incorrect endpoint routing or TLS.');
    }
}
echo "PASS: Neon endpoint routing preserves pooling and TLS; other hosts unchanged.\n";
