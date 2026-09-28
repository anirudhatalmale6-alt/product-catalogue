<?php
/**
 * Imports supplied product photographs using a written-down mapping.
 *
 *     php tools/import_photos.php <folder>            # show what it would do
 *     php tools/import_photos.php <folder> --apply    # actually do it
 *
 * The mapping lives in tools/data/photo_map.php - filename to product slug -
 * because the files arrive named after a stock library id and there is nothing
 * to match on automatically. Matching by filename is what the admin panel's
 * bulk upload does; this is the tool for when that cannot work.
 *
 * Per photo it resizes to fit 1600x1600 (the same ceiling the admin upload
 * uses, which also takes this set from ~100 MB to under 6 MB), writes
 * public/uploads/products/photos/<slug>-N.jpg, inserts a product_images row -
 * the first for a product being the main one - and removes that product's
 * "image to follow" placeholder ROW so the gallery never shows a real
 * photograph beside a plate saying there isn't one. The placeholder FILE is
 * left on disk.
 *
 * Nothing is written without --apply. It refuses to start if any filename in
 * the mapping is missing or names a product that does not exist, and it skips
 * anything already imported, so running it twice is safe.
 */

if (!defined('APP_ROOT')) {
    require dirname(__DIR__) . '/app/bootstrap.php';
}

/**
 * Does the work and returns a report. Writes only when $apply is true.
 *
 * Separated from the command-line handling below because the live server has
 * no shell, so the same code has to be callable from a one-time web runner.
 * Two copies of an import that deletes rows is exactly the sort of thing that
 * drifts apart and then behaves differently where it matters.
 *
 * @return array{ok: bool, lines: string[], imported: int, skipped: int,
 *                problems: string[], summary: string}
 */
function import_photos(string $dir, bool $apply, bool $filesAlreadyInPlace = false): array
{
    $report = ['ok' => false, 'lines' => [], 'imported' => 0, 'skipped' => 0,
               'problems' => [], 'summary' => ''];

    $map = require __DIR__ . '/data/photo_map.php';
    $dir = rtrim($dir, '/');

    if (!is_dir($dir)) {
        $report['problems'][] = "not a folder: {$dir}";
        return $report;
    }

    // --- Check everything before touching anything --------------------------
    $products = [];
    foreach ($map as $file => $slug) {
        // The product has to be looked up whether or not the source file is
        // here, because the main loop indexes $products by slug for every
        // entry in the map. Checking the file first and skipping on a miss
        // left an undefined key behind.
        if (!isset($products[$slug])) {
            $p = Database::one(
                'SELECT id, name, slug FROM products WHERE slug = ?', [$slug]);
            if (!$p) {
                $report['problems'][] = "no product with slug: {$slug}";
                continue;
            }
            $products[$slug] = $p;
        }

        // On the live server the resized images are uploaded straight into
        // public/uploads/products/photos, because sending ~100 MB of originals
        // over FTP so the server can shrink them again would be silly. In that
        // mode a missing SOURCE is fine as long as the DESTINATION is there.
        if (!is_file($dir . '/' . $file) && !$filesAlreadyInPlace) {
            $report['problems'][] = "missing file: {$file}";
        }
    }
    if ($report['problems']) {
        return $report;
    }

    $report['lines'][] = sprintf('%d photo(s) -> %d product(s)%s',
        count($map), count($products), $apply ? '' : '   (dry run)');

    $targetDir = UPLOAD_DIR . '/products/photos';
    if ($apply && !is_dir($targetDir)
        && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $report['problems'][] = "could not create {$targetDir}";
        return $report;
    }

    $seen = [];
    foreach ($map as $file => $slug) {
        $product = $products[$slug];
        $n       = ($seen[$slug] = ($seen[$slug] ?? 0) + 1);
        $isFirst = $n === 1;
        $rel     = 'products/photos/' . $slug . '-' . $n . '.jpg';
        $src     = $dir . '/' . $file;

        // Already imported? Skip rather than inserting a second identical row.
        $exists = Database::one(
            'SELECT id FROM product_images WHERE product_id = ? AND file_path = ?',
            [$product['id'], $rel]);
        if ($exists) {
            $report['skipped']++;
            $report['lines'][] = sprintf('  %-30s -> %-22s already imported',
                $file, $product['name']);
            continue;
        }

        $destAbs = UPLOAD_DIR . '/' . $rel;
        $haveSrc = is_file($src);

        if (!$haveSrc && !is_file($destAbs)) {
            $report['problems'][] = "neither source nor destination for {$file}";
            continue;
        }

        [$w, $h] = $haveSrc ? (getimagesize($src) ?: [0, 0]) : [0, 0];
        $report['lines'][] = sprintf('  %-30s -> %-22s %s%s',
            $file, $product['name'], $isFirst ? 'main ' : 'extra',
            $w ? "  ({$w}x{$h})" : '');

        if (!$apply) {
            continue;
        }

        // Only resize when we actually hold the original.
        if ($haveSrc) {
            $img = @imagecreatefromjpeg($src);
            if (!$img) {
                $report['problems'][] = "could not read {$file}";
                continue;
            }

            $scale = min(1, 1600 / max($w, $h));
            $nw    = max(1, (int) round($w * $scale));
            $nh    = max(1, (int) round($h * $scale));

            $out = imagecreatetruecolor($nw, $nh);
            // These are cut-outs on white. Filling white first means any stray
            // transparency lands on white rather than on black.
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagejpeg($out, UPLOAD_DIR . '/' . $rel, 85);
            imagedestroy($out);
            imagedestroy($img);
        }

        if ($isFirst) {
            Database::run(
                "DELETE FROM product_images
                  WHERE product_id = ? AND file_path LIKE 'products/catalogue/%'",
                [$product['id']]);
        }

        Database::run(
            'INSERT INTO product_images
                (product_id, file_path, alt_text, is_primary, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$product['id'], $rel, $product['name'], $isFirst ? 1 : 0, $n - 1]);

        $report['imported']++;
    }

    $withReal = (int) Database::scalar(
        "SELECT COUNT(DISTINCT product_id) FROM product_images
          WHERE file_path LIKE 'products/photos/%'");
    $stillPlaceholder = (int) Database::scalar(
        "SELECT COUNT(*) FROM products p
          WHERE p.is_active = 1
            AND NOT EXISTS (SELECT 1 FROM product_images i
                             WHERE i.product_id = p.id
                               AND i.file_path LIKE 'products/photos/%')");

    $report['ok']      = true;
    $report['summary'] = sprintf(
        '%d imported, %d already there. %d product(s) now have a real '
        . 'photograph; %d still on placeholders.',
        $report['imported'], $report['skipped'], $withReal, $stillPlaceholder);

    return $report;
}

// --- Command line -----------------------------------------------------------

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $dir   = $argv[1] ?? null;
    $apply = in_array('--apply', $argv, true);

    if (!$dir) {
        fwrite(STDERR, "Usage: php tools/import_photos.php <folder> [--apply]\n");
        exit(1);
    }

    $r = import_photos($dir, $apply);

    echo implode("\n", $r['lines']), "\n";
    if ($r['problems']) {
        fwrite(STDERR, "\nRefusing to run / problems:\n  "
            . implode("\n  ", $r['problems']) . "\n");
        exit(1);
    }
    echo "\n", $apply ? $r['summary'] : 'Nothing was changed. Re-run with --apply.', "\n";
    exit(0);
}
