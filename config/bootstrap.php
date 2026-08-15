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

set_exception_handler(function (Throwable $ex): void {
    error_log('[Nabhrang] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    echo '<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>त्रुटी</title><link rel="stylesheet" href="/assets/css/nabhrang.css"></head><body class="login-page"><main class="login-card" data-testid="server-error-page"><h1>काहीतरी चुकले</h1><p>तांत्रिक अडचण आली आहे. कृपया थोड्या वेळाने पुन्हा प्रयत्न करा.</p><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/" data-testid="error-back-home-link">← मुख्य पानावर परत</a></main></body></html>';
    exit;
});

function e(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid security token.'); } }

function setting(PDO $pdo, string $key, string $language = 'mr'): string {
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare('SELECT setting_value_mr, setting_value_en FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $cache[$key] = $stmt->fetch() ?: ['setting_value_mr' => '', 'setting_value_en' => ''];
    }
    return (string) ($cache[$key]['setting_value_' . ($language === 'en' ? 'en' : 'mr')] ?? '');
}

function require_admin(): void { if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; } }
function require_member(): void { if (empty($_SESSION['member_id'])) { header('Location: /member/login.php'); exit; } }
function require_role(string $role): void {
    require_admin();
    $current = $_SESSION['admin_role'] ?? '';
    if ($role === 'super_admin' && $current !== 'super_admin') { http_response_code(403); exit('Access denied.'); }
    // editor role check: super_admin also passes
}

function member_history(PDO $pdo, int $memberId, string $action, string $details = ''): void {
    $stmt = $pdo->prepare('INSERT INTO member_history(member_id,action,details,changed_by) VALUES(?,?,?,?)');
    $stmt->execute([$memberId, $action, $details, $_SESSION['admin_id'] ?? null]);
}

function next_membership_id(PDO $pdo): string {
    $prefix = setting($pdo, 'membership_id_prefix') ?: 'NB';
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(membership_id,'-',-1) AS UNSIGNED)),0) AS seq FROM members WHERE membership_id LIKE ?");
    $stmt->execute([$like]);
    $next = ((int) ($stmt->fetch()['seq'] ?? 0)) + 1;
    return sprintf('%s-%s-%05d', $prefix, $year, $next);
}

function slugify(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);
    return trim((string) $value, '-') ?: 'content-' . bin2hex(random_bytes(4));
}

function audit(PDO $pdo, string $action, string $entity, ?int $entityId = null): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$_SESSION['admin_id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}

/**
 * Save an uploaded file into /uploads/<subdir>/ with a random-safe name.
 * Returns web-relative path (e.g. "/uploads/qr/abc123.png") or null when skipped/invalid.
 */
function save_upload(array $file, string $subdir, array $allowedExt, int $maxBytes = 5242880): ?string {
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > $maxBytes) return null;
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;

    // Light MIME sanity check when finfo is available
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        $imageMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (isset($imageMimes[$ext]) && $mime !== $imageMimes[$ext]) return null;
        if ($ext === 'pdf' && $mime && $mime !== 'application/pdf') return null;
    }

    $root = dirname(__DIR__) . '/uploads/' . trim($subdir, '/');
    if (!is_dir($root)) @mkdir($root, 0755, true);
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $target = $root . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) return null;
    return '/uploads/' . trim($subdir, '/') . '/' . $name;
}

function maintenance_guard(PDO $pdo): void {
    if (setting($pdo, 'maintenance_mode') !== '1') return;
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    if (str_starts_with($path, '/admin') || str_starts_with($path, '/assets') || str_starts_with($path, '/uploads')) return;
    http_response_code(503);
    $msg = setting($pdo, 'maintenance_message') ?: 'साइट देखभालीसाठी बंद आहे.';
    $name = setting($pdo, 'organization_name') ?: 'नभरंग';
    echo '<!doctype html><html lang="mr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($name) . '</title><link rel="stylesheet" href="/assets/css/nabhrang.css"></head><body class="login-page"><main class="login-card" data-testid="maintenance-page"><div class="brand">' . e($name) . '<small>देखभाल सुरू</small></div><h1>थोडा विसावा</h1><p>' . e($msg) . '</p><a class="btn btn-outline" style="color:var(--velvet);border-color:var(--velvet)" href="/admin/login.php" data-testid="maintenance-admin-link">प्रशासकीय प्रवेश</a></main></body></html>';
    exit;
}

function unique_blog_slug(PDO $pdo, string $title, ?int $ignoreId = null): string {
    $base = slugify($title);
    $slug = $base;
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM blogs WHERE slug = ? AND (id <> ? OR ? IS NULL) LIMIT 1');
        $stmt->execute([$slug, $ignoreId ?? 0, $ignoreId]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . (++$i);
    }
}

function csv_download(string $filename, array $header, iterable $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM so Excel opens Marathi correctly
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

function admin_can(string $capability): bool {
    $role = $_SESSION['admin_role'] ?? '';
    if ($role === 'super_admin') return true;
    // editors: content only
    $editorCaps = ['content', 'blogs', 'events', 'gallery', 'videos', 'publications', 'notifications'];
    return in_array($capability, $editorCaps, true);
}
