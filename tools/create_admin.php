<?php
/**
 * Creates or resets an admin account. Run it from the command line:
 *
 *     php tools/create_admin.php <username> <password> ["Full Name"]
 *
 * If the username already exists its password is reset instead.
 * This file only runs on the CLI - opening it in a browser does nothing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script only runs from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
$fullName = $argv[3] ?? null;

if (!$username || !$password) {
    fwrite(STDERR, "Usage: php tools/create_admin.php <username> <password> [\"Full Name\"]\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Choose a password of at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = Database::one('SELECT id FROM admin_users WHERE username = ?', [$username]);

if ($existing) {
    Database::run(
        'UPDATE admin_users SET password_hash = ?, full_name = COALESCE(?, full_name), is_active = 1
          WHERE id = ?',
        [$hash, $fullName, $existing['id']]);
    echo "Password reset for '{$username}'.\n";
} else {
    Database::run(
        'INSERT INTO admin_users (username, full_name, password_hash) VALUES (?, ?, ?)',
        [$username, $fullName, $hash]);
    echo "Admin user '{$username}' created.\n";
}

echo "Sign in at:  <your site>/admin/login\n";
