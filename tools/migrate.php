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

/**
 * Split a migration file into statements.
 *
 * The first version of this split on "semicolon followed by a newline", which
 * quietly assumed one statement per line. A migration written as
 *
 *     PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
 *
 * therefore arrived at the driver as one string and failed with a syntax error
 * pointing at the middle of the line - a confusing message for a file that is
 * perfectly valid SQL. So this walks the text instead: it splits on every
 * semicolon that is not inside a quoted string, and strips `--` comments,
 * which is the only comment style these files use.
 *
 * @return string[] non-empty statements, in order
 */
function split_sql(string $sql): array
{
    $out = [];
    $cur = '';
    $len = strlen($sql);
    $quote = null;        // the quote character we are inside, or null
    $inComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($inComment) {
            if ($ch === "\n") {
                $inComment = false;
                $cur .= $ch;
            }
            continue;
        }

        if ($quote !== null) {
            $cur .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {   // escaped character
                $cur .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $inComment = true;
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $cur .= $ch;
            continue;
        }
        if ($ch === ';') {
            if (trim($cur) !== '') {
                $out[] = trim($cur);
            }
            $cur = '';
            continue;
        }
        $cur .= $ch;
    }

    if (trim($cur) !== '') {
        $out[] = trim($cur);
    }
    return $out;
}

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

    $sql        = file_get_contents($f);
    $statements = split_sql($sql);

    $ran = 0;
    foreach ($statements as $stmt) {
        Database::run($stmt);
        $ran++;
    }

    Database::run('INSERT INTO schema_migrations (filename) VALUES (?)', [basename($f)]);
    echo "         {$ran} statement(s) ok\n";
}

echo $apply
    ? "Done.\n"
    : "\nNothing was changed. Re-run with --apply to actually run these.\n";
