<?php
/**
 * Loaded by public/index.php before anything else happens.
 * Sets up config, error handling, the session and the database connection.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('PUBLIC_DIR', APP_ROOT . '/public');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');

// --- Config -----------------------------------------------------------------
$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo '<h1>Setup needed</h1><p>Copy <code>app/config.sample.php</code> to '
       . '<code>app/config.php</code> and enter your database details.</p>';
    exit;
}
$config = require $configFile;

// --- Error handling ---------------------------------------------------------
if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
}

// --- Session ----------------------------------------------------------------
// Hardened cookie flags. The admin session lives here.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($config['security']['https_only']),
        'samesite' => 'Lax',
    ]);
    session_name('catalogue_sid');
    session_start();
}

// --- Libraries --------------------------------------------------------------
require APP_DIR . '/lib/Database.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/Auth.php';
require APP_DIR . '/lib/ImageUploader.php';
require APP_DIR . '/lib/ProductRepository.php';
require APP_DIR . '/lib/CategoryRepository.php';
require APP_DIR . '/lib/OriginRepository.php';

Database::configure($config['db']);

// --- Settings (site name, currency ...) -------------------------------------
$GLOBALS['config']   = $config;
$GLOBALS['settings'] = load_settings();
