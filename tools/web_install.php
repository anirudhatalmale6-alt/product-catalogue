<?php
/**
 * One-time installer, for hosts where there is no SSH and no phpMyAdmin access.
 *
 * On GoDaddy shared hosting a cPanel *subaccount* can upload files but cannot
 * open phpMyAdmin, so the database has to be filled from the web instead. This
 * script does that: it runs sql/schema.sql, then sql/catalogue_data.sql, then
 * creates the first admin login, then counts what actually landed.
 *
 * It refuses to do anything unless tools/install_config.php exists and the
 * token in the URL matches the one inside it. Upload that file, run this, then
 * DELETE BOTH. The last step of a successful run deletes install_config.php
 * for you and tells you to remove this file too.
 *
 *     /catalogue/tools/web_install.php?token=XXXX&action=check
 *     /catalogue/tools/web_install.php?token=XXXX&action=install
 *     /catalogue/tools/web_install.php?token=XXXX&action=install&force=1
 *
 * 'check' is read-only: it reports PHP version, extensions, folder
 * permissions and whether the database credentials work. Always run it first.
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$root       = dirname(__DIR__);
$configFile = __DIR__ . '/install_config.php';

function out(string $line = ''): void { echo $line . "\n"; }
function fail(string $why): void { out('FAILED: ' . $why); exit(1); }

// --- Guard ------------------------------------------------------------------

if (!is_file($configFile)) {
    http_response_code(404);
    exit("Not found.\n");
}

$install = require $configFile;

$given = (string)($_GET['token'] ?? '');
if ($given === '' || !isset($install['token']) || !hash_equals((string)$install['token'], $given)) {
    http_response_code(403);
    exit("Not found.\n");
}

$action = $_GET['action'] ?? 'check';
$force  = isset($_GET['force']);

// --- Database connection ----------------------------------------------------

$configPath = $root . '/app/config.php';
if (!is_file($configPath)) {
    fail('app/config.php is missing. Upload it before running the installer.');
}
$config = require $configPath;
$db     = $config['db'];

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'], $db['port'] ?? 3306, $db['name'], $db['charset'] ?? 'utf8mb4');

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fail('could not connect to the database - ' . $e->getMessage());
}

out('Database connection OK  (' . $db['name'] . ' as ' . $db['user'] . ')');
out('PHP ' . PHP_VERSION . ' on ' . php_uname('n'));
out('');

// --- check ------------------------------------------------------------------

if ($action === 'check') {
    out('== Extensions ==');
    foreach (['pdo_mysql', 'gd', 'mbstring', 'fileinfo', 'json'] as $ext) {
        out(sprintf('  %-10s %s', $ext, extension_loaded($ext) ? 'yes' : 'MISSING'));
    }

    out('');
    out('== Folders ==');
    foreach (['public/uploads', 'public/uploads/products', 'app'] as $rel) {
        $path = $root . '/' . $rel;
        out(sprintf('  %-24s %s  %s',
            $rel,
            is_dir($path) ? 'exists' : 'MISSING',
            is_dir($path) ? (is_writable($path) ? 'writable' : 'not writable') : ''));
    }

    out('');
    out('== Hidden files (these are easy to lose over FTP) ==');
    foreach (['.htaccess', 'public/.htaccess', 'public/uploads/.htaccess'] as $rel) {
        out(sprintf('  %-28s %s', $rel, is_file($root . '/' . $rel) ? 'present' : 'MISSING'));
    }

    out('');
    out('== Existing tables ==');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    out($tables ? '  ' . implode(', ', $tables) : '  (none - a clean database)');

    out('');
    out('Nothing was changed. Add &action=install to load the catalogue.');
    exit;
}

if ($action !== 'install') {
    fail('unknown action.');
}

// --- Refuse to wipe real data ----------------------------------------------

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if (in_array('products', $tables, true) && !$force) {
    $counts = [];
    foreach (['products', 'product_pricing', 'enquiries'] as $t) {
        if (in_array($t, $tables, true)) {
            $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        }
    }
    if (array_sum($counts) > 0) {
        out('The database already has data in it:');
        foreach ($counts as $t => $n) {
            out(sprintf('  %-16s %d rows', $t, $n));
        }
        out('');
        out('schema.sql DROPS every table, so running this now would delete the');
        out('price sheet and any stored enquiries as well as the products.');
        fail('refusing to overwrite. Add &force=1 only if you are certain.');
    }
}

// --- Run the SQL files ------------------------------------------------------

/**
 * Splits a dump into statements. Both files are single-statement-per-line or
 * plain multi-line CREATE TABLE blocks - no routines, no DELIMITER changes -
 * so a statement ends at the first line that ends in a semicolon.
 */
function statements(string $sql): array
{
    $out     = [];
    $current = '';

    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $current .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $out[]   = trim($current);
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $out[] = trim($current);
    }
    return $out;
}

foreach (['sql/schema.sql', 'sql/catalogue_data.sql'] as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        fail($rel . ' is missing - the upload is incomplete.');
    }

    $list = statements((string)file_get_contents($path));
    out(sprintf('Running %s  (%d statements)', $rel, count($list)));

    $n = 0;
    foreach ($list as $i => $stmt) {
        try {
            $pdo->exec($stmt);
            $n++;
        } catch (PDOException $e) {
            out('');
            out('Statement ' . ($i + 1) . ' failed:');
            out('  ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 200));
            fail($e->getMessage());
        }
    }
    out('  ' . $n . ' statements OK');
}

out('');

// --- First admin login ------------------------------------------------------

$user = (string)($install['admin_user'] ?? '');
$pass = (string)($install['admin_pass'] ?? '');

if ($user === '' || strlen($pass) < 10) {
    fail('install_config.php needs admin_user and an admin_pass of 10+ characters.');
}

$hash     = password_hash($pass, PASSWORD_DEFAULT);
$existing = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
$existing->execute([$user]);
$row = $existing->fetch();

if ($row) {
    $pdo->prepare('UPDATE admin_users SET password_hash = ?, is_active = 1 WHERE id = ?')
        ->execute([$hash, $row['id']]);
    out("Admin '{$user}' already existed - password reset.");
} else {
    $pdo->prepare('INSERT INTO admin_users (username, full_name, password_hash) VALUES (?, ?, ?)')
        ->execute([$user, $install['admin_name'] ?? null, $hash]);
    out("Admin '{$user}' created.");
}

// --- Verify -----------------------------------------------------------------

out('');
out('== What actually landed ==');

$expected = [
    'categories'     => 8,
    'origins'        => 5,
    'products'       => 197,
    'product_images' => 213,
];

$bad = false;
foreach ($expected as $table => $want) {
    $got = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $ok  = $got === $want;
    $bad = $bad || !$ok;
    out(sprintf('  %-16s %5d   expected %5d   %s', $table, $got, $want, $ok ? 'OK' : 'MISMATCH'));
}

foreach (['product_specs', 'product_pricing', 'enquiries', 'admin_users', 'settings'] as $table) {
    $got = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    out(sprintf('  %-16s %5d', $table, $got));
}

out('');

// --- Clean up ---------------------------------------------------------------

if (@unlink($configFile)) {
    out('install_config.php deleted (it held the token and the admin password).');
} else {
    out('COULD NOT delete install_config.php - remove it by hand, it holds the');
    out('admin password in plain text.');
}

out('Now delete tools/web_install.php as well.');
out('');
out($bad
    ? 'Finished, but a count did not match. Read the table above before going live.'
    : 'Finished. Everything matched.');
