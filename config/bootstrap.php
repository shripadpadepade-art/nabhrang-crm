<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
session_name('nabhrang_admin');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['db']['host'], $config['db']['port'], $config['db']['name'], $config['db']['charset']);
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(503);
    exit('Database connection is not available. Please check your hosting configuration.');
}

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid security token.'); } }
function setting(PDO $pdo, string $key, string $language = 'mr'): string {
    $stmt = $pdo->prepare('SELECT setting_value_mr, setting_value_en FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]); $row = $stmt->fetch();
    return (string) ($row['setting_value_' . ($language === 'en' ? 'en' : 'mr')] ?? '');
}
function require_admin(): void { if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; } }
function audit(PDO $pdo, string $action, string $entity, ?int $entityId = null): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$_SESSION['admin_id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}