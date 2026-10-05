<?php
return [
    'environment' => 'local', // Change to production before hosting.
    'app_url' => 'http://localhost:8080', // No trailing slash; include subdirectory if used.
    'database' => [
        'driver' => 'mysql', // mysql recommended; sqlite supported for local testing.
        'host' => '127.0.0.1', 'port' => 3306,
        'name' => 'bou_cse_notes', 'user' => 'root', 'password' => '',
        'sqlite_path' => __DIR__ . '/../storage/local.sqlite',
    ],
    'mail' => [
        'driver' => 'log', // Local only. Use smtp in production.
        'host' => '', 'port' => 587, 'encryption' => 'tls',
        'username' => '', 'password' => '',
        'from' => 'noreply@example.com', 'from_name' => 'BOU CSE Notes',
    ],
    'storage_path' => __DIR__ . '/../storage',
    'support_email' => 'support@example.com',
    'session_lifetime' => 7200,
];
