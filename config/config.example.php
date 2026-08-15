<?php
declare(strict_types=1);

/**
 * Nabhrang · Copy this file to config.php and fill in your cPanel database credentials.
 * Alternatively, set the NABHRANG_* environment variables from your hosting panel.
 */
return [
    'db' => [
        'host'    => getenv('NABHRANG_DB_HOST') ?: 'localhost',
        'port'    => getenv('NABHRANG_DB_PORT') ?: '3306',
        'name'    => getenv('NABHRANG_DB_NAME') ?: 'nabhrang',
        'user'    => getenv('NABHRANG_DB_USER') ?: 'root',
        'pass'    => getenv('NABHRANG_DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url'    => getenv('NABHRANG_BASE_URL') ?: '/',
        'environment' => getenv('NABHRANG_ENV') ?: 'production',
    ],
];
