<?php
/**
 * A fake Shopify Admin API, so the sync can be proven end to end without
 * touching a real store.
 *
 *     php -S 127.0.0.1:8499 shots/mock_shopify.php
 *
 * It is deliberately awkward in the ways the real one is: it pages, it demands
 * the token header, and it returns a 429 with Retry-After the first time it is
 * asked for page one, so the retry path is exercised by a normal run instead of
 * only existing in theory.
 */

$token = 'shpat_faketoken_for_tests';

// --- auth ------------------------------------------------------------------
$sent = $_SERVER['HTTP_X_SHOPIFY_ACCESS_TOKEN'] ?? '';
if ($sent !== $token) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['errors' => '[API] Invalid API key or access token']);
    return true;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path = preg_replace('#^/admin/api/[^/]+/#', '', $path);

header('Content-Type: application/json');

// --- shop.json -------------------------------------------------------------
if ($path === 'shop.json') {
    echo json_encode(['shop' => [
        'name'             => 'Disruptive Sourcing Test Shop',
        'myshopify_domain' => 'ds-test.myshopify.com',
        'currency'         => 'CAD',
    ]]);
    return true;
}

// --- products.json ---------------------------------------------------------
if ($path === 'products.json') {
    $all = [
        // in stock, tracked
        ['id' => 1001, 'title' => 'Dragon fruit', 'handle' => 'dragon-fruit',
         'status' => 'active', 'published_at' => '2026-09-01T00:00:00Z',
         'image' => ['src' => 'https://cdn.example/df.jpg'],
         'variants' => [['id' => 5001, 'sku' => 'DF-01', 'price' => '12.50',
                         'inventory_quantity' => 24, 'inventory_management' => 'shopify',
                         'inventory_policy' => 'deny']]],
        // tracked and at zero - must come out NOT available
        ['id' => 1002, 'title' => 'Durian', 'handle' => 'durian',
         'status' => 'active', 'published_at' => '2026-09-01T00:00:00Z',
         'variants' => [['id' => 5002, 'sku' => 'DU-01', 'price' => '31.00',
                         'inventory_quantity' => 0, 'inventory_management' => 'shopify',
                         'inventory_policy' => 'deny']]],
        // zero but set to keep selling - must come out AVAILABLE
        ['id' => 1003, 'title' => 'Young coconut', 'handle' => 'young-coconut',
         'status' => 'active', 'published_at' => '2026-09-01T00:00:00Z',
         'variants' => [['id' => 5003, 'sku' => 'YC-01', 'price' => '8.00',
                         'inventory_quantity' => 0, 'inventory_management' => 'shopify',
                         'inventory_policy' => 'continue']]],
        // a draft - must come out NOT available even with stock
        ['id' => 1004, 'title' => 'Mangosteen', 'handle' => 'mangosteen',
         'status' => 'draft', 'published_at' => null,
         'variants' => [['id' => 5004, 'sku' => 'MG-01', 'price' => '19.00',
                         'inventory_quantity' => 40, 'inventory_management' => 'shopify',
                         'inventory_policy' => 'deny']]],
        // stock not tracked at all - always sellable
        ['id' => 1005, 'title' => 'Avocados', 'handle' => 'avocados',
         'status' => 'active', 'published_at' => '2026-09-01T00:00:00Z',
         'variants' => [['id' => 5005, 'sku' => null, 'price' => '4.25',
                         'inventory_quantity' => null, 'inventory_management' => null,
                         'inventory_policy' => 'deny']]],
    ];

    // Force one 429 on the very first request so the retry is tested for real.
    $flag = sys_get_temp_dir() . '/mock_shopify_429_' . getmypid();
    $stateFile = __DIR__ . '/.mock_shopify_state';
    if (!file_exists($stateFile)) {
        file_put_contents($stateFile, '1');
        http_response_code(429);
        header('Retry-After: 1');
        echo json_encode(['errors' => 'Exceeded 2 calls per second']);
        return true;
    }

    // Two pages of 3 and 2, so the Link header path is exercised.
    $page = isset($_GET['page_info']) ? (int) $_GET['page_info'] : 1;
    $slice = $page === 1 ? array_slice($all, 0, 3) : array_slice($all, 3);
    if ($page === 1) {
        $next = 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 8499)
              . '/admin/api/2025-01/products.json?limit=3&page_info=2';
        header('Link: <' . $next . '>; rel="next"');
    }
    echo json_encode(['products' => $slice]);
    return true;
}

http_response_code(404);
echo json_encode(['errors' => 'Not Found']);
return true;
