<?php
declare(strict_types=1);
require __DIR__ . '/../config/bootstrap.php';
$username = $argv[1] ?? null; $name = $argv[2] ?? null;
if (!$username || !$name) exit("Usage: php scripts/create_admin.php username \"name\"\n");
$password = trim((string) shell_exec('stty -echo 2>/dev/null; printf "Password: " >&2; read password; printf "%s" "$password"; stty echo 2>/dev/null'));
if (!$password) exit("A password is required.\n");
$stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, full_name_mr, role) VALUES (?, ?, ?, ?)' );
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, 'super_admin']);
echo "Admin created.\n";