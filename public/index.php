<?php
/**
 * Front controller. Every request lands here (see .htaccess) and is dispatched
 * to a controller file in app/controllers/.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

// --- Work out the route ------------------------------------------------------
// Works both with the .htaccess rewrite (/product/hammer) and without it
// (/index.php?r=product/hammer), so the site still runs on a host where
// mod_rewrite is off.
$route = $_GET['r'] ?? null;

if ($route === null) {
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $base = base_url();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $route = trim(rawurldecode($uri), '/');
    if (str_starts_with($route, 'index.php')) {
        $route = trim(substr($route, strlen('index.php')), '/');
    }
}

$route = trim((string) $route, '/');
$_GET['r'] = $route;                       // views use this to rebuild links
$segments  = $route === '' ? [] : explode('/', $route);

// --- Security headers --------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('X-XSS-Protection: 0');

// --- Dispatch ----------------------------------------------------------------
$section = $segments[0] ?? '';

try {
    if ($section === 'admin') {
        require APP_DIR . '/controllers/admin.php';
        admin_dispatch(array_slice($segments, 1));
    } else {
        require APP_DIR . '/controllers/catalogue.php';
        catalogue_dispatch($segments);
    }
} catch (Throwable $ex) {
    error_log('[catalogue] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (config('debug')) {
        throw $ex;
    }
    http_response_code(500);
    view('errors', [
        'title'   => 'Something went wrong',
        'message' => 'An unexpected error occurred. It has been written to the server error log.',
    ]);
}
