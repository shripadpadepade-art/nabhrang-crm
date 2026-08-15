<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('NABHRANG_DB_HOST'), 'port' => getenv('NABHRANG_DB_PORT'),
        'name' => getenv('NABHRANG_DB_NAME'), 'user' => getenv('NABHRANG_DB_USER'), 'pass' => getenv('NABHRANG_DB_PASS'),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => getenv('NABHRANG_BASE_URL') ?: '/',
        'environment' => getenv('NABHRANG_ENV') ?: 'development',
    ],
];