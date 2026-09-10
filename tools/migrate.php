<?php
/**
 * Applies any .sql file in sql/migrations/ that has not been applied yet.
 *
 *     php tools/migrate.php          # show what would run, change nothing
 *     php tools/migrate.php --apply  # actually run them
 *
 * Applied filenames are recorded in a schema_migrations table, so running it
 * twice does nothing the second time. Each file runs inside a transaction
 * where MySQL allows it - note that CREATE TABLE and other DDL commit
 * implicitly in MySQL, so a half-finished migration file cannot be rolled
 * back. Keep each file small and additive for that reason.
 *
 * CLI only. Opening it in a browser does nothing.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script only runs from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$dir   = dirname(__DIR__) . '/sql/migrations';

if (!is_dir($dir)) {
    fwrite(STDERR, "No sql/migrations directory.\n");
    exit(1);
}

Database::run(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename    VARCHAR(190) NOT NULL,
        applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (filename)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$done = [];
foreach (Database::all('SELECT filename FROM schema_migrations') as $r) {
    $done[$r['filename']] = true;
}

$files = glob($dir . '/*.sql') ?: [];
sort($files);

$pending = [];
foreach ($files as $f) {
    if (!isset($done[basename($f)])) {
        $pending[] = $f;
    }
}

if (!$pending) {
    echo "Nothing to do - all " . count($files) . " migration(s) already applied.\n";
    exit(0);
}

foreach ($pending as $f) {
    echo ($apply ? 'APPLYING ' : 'PENDING  ') . basename($f) . "\n";
    if (!$apply) {
        continue;
    }

    $sql = file_get_contents($f);
    // Split on semicolons at end of line. The migration files are hand-written
    // and contain no stored routines or string literals with semicolons in
    // them, so this is sufficient here and stays readable.
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));

    $ran = 0;
    foreach ($statements as $stmt) {
        // Strip comment-only fragments.
        $bare = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
        if ($bare === '') {
            continue;
        }
        Database::run(rtrim($stmt, "; \t\n\r"));
        $ran++;
    }

    Database::run('INSERT INTO schema_migrations (filename) VALUES (?)', [basename($f)]);
    echo "         {$ran} statement(s) ok\n";
}

echo $apply
    ? "Done.\n"
    : "\nNothing was changed. Re-run with --apply to actually run these.\n";
