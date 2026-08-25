<?php
/**
 * Small functions used across the views and controllers.
 */

/** Escape for HTML output. Short name because it is used everywhere. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Settings row cache, loaded once per request in bootstrap. */
function load_settings(): array
{
    try {
        $rows = Database::all('SELECT setting_key, setting_value FROM settings');
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}

function setting(string $key, $default = null)
{
    return $GLOBALS['settings'][$key] ?? $default;
}

function config(string $path, $default = null)
{
    $node = $GLOBALS['config'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

/**
 * Base URL of the app, e.g. "" when at the domain root or "/catalogue"
 * when installed in a sub-folder. Detected from the front controller so the
 * same code works in both cases without editing anything.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_url');
    if ($configured !== null && $configured !== '') {
        return $base = rtrim($configured, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '.') {
        $dir = '';
    }
    // When the whole project sits in the web root and the root .htaccess maps
    // requests into public/, SCRIPT_NAME comes back as /public/index.php.
    // Visitors typed the address without "/public", so drop it from the links.
    if (str_ends_with($dir, '/public') || $dir === '/public') {
        $dir = substr($dir, 0, -strlen('/public'));
    }
    return $base = $dir;
}

/** Build an in-app URL. url('product/hammer') -> "/shop/product/hammer" */
function url(string $path = '', array $query = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    $u = rtrim($u, '/');
    if ($u === '') {
        $u = '/';
    }
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

/** URL of an uploaded file stored as "products/xyz.jpg". */
function upload_url(?string $relative): string
{
    if (!$relative) {
        return url('assets/img/placeholder.svg');
    }
    return url('uploads/' . ltrim($relative, '/'));
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Money formatting driven by the settings table. */
function money($amount): string
{
    $symbol = setting('currency_symbol', '$');
    return $symbol . number_format((float) $amount, 2);
}

/**
 * The label the buyer-facing pages show where a price would otherwise sit.
 * Editable under Settings.
 *
 * There is no price_on_request() test any more because there is nothing to
 * test: the public catalogue carries no pricing at all, so every product shows
 * this label. Internal figures live in product_pricing and are only ever read
 * by the admin panel through PricingRepository.
 */
function price_request_label(): string
{
    return setting('price_request_label', 'Price on request');
}

/** Human labels + CSS modifier for the stock_status enum. */
function stock_label(string $status): string
{
    return [
        'in_stock'      => 'In stock',
        'low_stock'     => 'Low stock',
        'out_of_stock'  => 'Out of stock',
        'preorder'      => 'Pre-order',
        'made_to_order' => 'Available to order',
        'discontinued'  => 'Discontinued',
    ][$status] ?? $status;
}

function stock_class(string $status): string
{
    return [
        'in_stock'      => 'ok',
        'low_stock'     => 'warn',
        'out_of_stock'  => 'bad',
        'preorder'      => 'info',
        'made_to_order' => 'info',
        'discontinued'  => 'muted',
    ][$status] ?? 'muted';
}

/** Every value the stock_status enum accepts, in display order. */
function stock_statuses(): array
{
    return ['in_stock', 'low_stock', 'made_to_order', 'preorder', 'out_of_stock', 'discontinued'];
}

/** URL-safe slug. Falls back to a random string for non-latin names. */
function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower(preg_replace('~[^a-zA-Z0-9]+~', '-', $text) ?? '');
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . bin2hex(random_bytes(3));
}

/**
 * Make a slug unique within a table, ignoring one row id when editing.
 * $table is never user input - it is a literal at every call site.
 */
function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $base = $slug;
    $n = 1;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        if (Database::one($sql . ' LIMIT 1', $params) === null) {
            return $slug;
        }
        $slug = $base . '-' . (++$n);
    }
}

// --- CSRF -------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * True when the posted token matches. Use this where the page can recover -
 * a public form whose contents are worth handing back rather than throwing
 * away behind an error page.
 */
function csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/** Called at the top of every admin POST handler. Dies on mismatch. */
function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('<h1>Session expired</h1><p>Please go back, reload the page and try again.</p>');
    }
}

// --- Flash messages ---------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// --- Views ------------------------------------------------------------------

/** Render a view file inside the shared layout. */
function view(string $template, array $data = [], string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $template . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/views/' . $layout . '.php';
}

function partial(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/partials/' . $template . '.php';
}

function not_found(string $message = 'Page not found'): never
{
    http_response_code(404);
    view('errors', ['message' => $message, 'title' => 'Not found']);
    exit;
}

/** Keeps existing query string values when building filter links. */
function with_query(array $changes): string
{
    $q = $_GET;
    unset($q['r']);
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    unset($q['page']);           // any filter change resets pagination
    if (isset($changes['page'])) {
        $q['page'] = $changes['page'];
    }
    $route = $_GET['r'] ?? '';
    return url($route, $q);
}

function old(string $key, $default = '')
{
    return $_SESSION['old'][$key] ?? $default;
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}
