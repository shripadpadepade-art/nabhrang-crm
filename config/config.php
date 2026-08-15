<?php
declare(strict_types=1);
function required_env(string $key): string { $value = getenv($key); if ($value === false || $value === '') { throw new RuntimeException("Missing required configuration: {$key}"); } return $value; }
return [
    'db' => [
        'host' => required_env('NABHRANG_DB_HOST'), 'port' => required_env('NABHRANG_DB_PORT'),
        'name' => required_env('NABHRANG_DB_NAME'), 'user' => required_env('NABHRANG_DB_USER'), 'pass' => required_env('NABHRANG_DB_PASS'),
        'charset' => 'utf8mb4',
    ],
    'app' => ['base_url' => required_env('NABHRANG_BASE_URL'), 'environment' => required_env('NABHRANG_ENV')],
];