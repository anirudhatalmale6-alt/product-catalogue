<?php
/**
 * Router for PHP's built-in server, so you can run the catalogue without
 * Apache while you are working on it:
 *
 *     php -S localhost:8000 -t public tools/router_dev.php
 *
 * Not used in production - Apache/nginx handle this with .htaccess or a
 * try_files rule. See DEPLOYMENT.md.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../public' . $path;

// Serve real files (CSS, JS, uploaded images) straight from disk.
if ($path !== '/' && is_file($file)) {
    // Never hand out a PHP file from the uploads folder, matching what the
    // .htaccess does on a real server.
    if (str_starts_with($path, '/uploads/') && preg_match('~\.(php|phtml|phar)$~i', $path)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../public/index.php';
