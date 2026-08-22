<?php
/**
 * Copy this file to config.php and fill in your own values.
 * config.php is the only file you need to edit when you move the site to a
 * new server. It is git-ignored so your credentials never end up in a repo.
 */

return [
    // --- Database -----------------------------------------------------------
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'catalogue',
        'user'     => 'catalogue_user',
        'pass'     => 'change-me',
        'charset'  => 'utf8mb4',
        // Shared hosts sometimes want a socket instead of a host/port.
        // Leave null unless your host tells you otherwise.
        'socket'   => null,
    ],

    // --- Site ---------------------------------------------------------------
    // Leave base_url null and it is detected automatically. Set it explicitly
    // if the site sits behind a proxy that rewrites the path.
    'base_url' => null,

    // --- Uploads ------------------------------------------------------------
    'uploads' => [
        'max_bytes'  => 6 * 1024 * 1024,   // 6 MB per image
        'max_width'  => 1600,              // larger images are downscaled
        'max_height' => 1600,
        'thumb_width'=> 500,
    ],

    // --- Security -----------------------------------------------------------
    'security' => [
        // Lock out an IP after this many failed logins within the window.
        'max_login_attempts'   => 8,
        'login_window_minutes' => 15,
        // Set true once you have HTTPS working (cookies become secure-only).
        'https_only'           => false,
    ],

    // Set to true on your own machine to see PHP errors on screen.
    // ALWAYS false on a live server.
    'debug' => false,
];
