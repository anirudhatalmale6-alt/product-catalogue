<?php
/**
 * Attach the Thai fruit reference data to the matching catalogue products.
 *
 * Source: the client-supplied "Thai FRUITS.pdf" export guide, extracted by
 * tools/extract_fruit_reference.py into tools/data/thai_fruit_reference.php.
 *
 * WHAT THIS DOES AND DOES NOT WRITE
 *
 * It writes botanical and handling facts only - Thai name, scientific name,
 * season, storage regime, typical uses, nutrition, and physical description
 * where the book gives one unambiguously. Every value is quoted from the book
 * and is grouped under a heading that names the source, so nobody reading the
 * product page mistakes it for Disruptive Sourcing's own trade terms.
 *
 * It does NOT write pack sizes, shelf lives as sold, MOQs, grades,
 * certifications, HS codes or prices. The book does not state them for these
 * products, and they are the client's own commercial terms to declare.
 *
 * Specs typed in the admin panel are never overwritten: only rows in the
 * reference group are replaced, so this can be re-run safely.
 *
 * Run:  php tools/import_fruit_reference.php
 *       php tools/import_fruit_reference.php --dry-run
 */

require dirname(__DIR__) . '/app/bootstrap.php';

/** The heading the imported rows sit under on the product page. */
const REFERENCE_GROUP = 'Reference (Thai DOA export guide)';

$dryRun = in_array('--dry-run', $argv, true);

$reference = require __DIR__ . '/data/thai_fruit_reference.php';

/**
 * The book's common name to the client's product name.
 *
 * Written out rather than fuzzy-matched. A near-match that quietly attaches
 * durian storage data to the wrong product is worse than no data at all, and on
 * a list this size an explicit table is something the client can actually read
 * and correct. Anything not named here is skipped and reported.
 */
$MAP = [
    'Banana'                         => 'Bananas',
    'Bread fruit'                    => 'Breadfruit',
    'Cantaloupe'                     => 'Cantaloupes',
    'Carambola, Star fruit'          => 'Starfruit',
    'Coconut'                        => 'Coconuts',
    'Dragon Fruit'                   => 'Dragon fruit',
    'Durian'                         => 'Durian',
    'Gandaria, Maprang, Marian plum' => 'Marian plums',
    'Grape'                          => 'Grapes',
    'Guava'                          => 'Guavas',
    'Jackfruit'                      => 'Jackfruit',
    'Jujube'                         => 'Jujubes',
    'Lime'                           => 'Limes',
    'Longan'                         => 'Longan',
    'Longkong'                       => 'Longkong',
    'Lychee, Lichi'                  => 'Lychees',
    'Mango'                          => 'Mangoes',
    'Mangosteen'                     => 'Mangosteen',
    'Papaya'                         => 'Papayas',
    'Passion fruit'                  => 'Passion fruit',
    'Pineapple'                      => 'Pineapples',
    'Pomegranate'                    => 'Pomegranates',
    'Pummelo, Pomelo, Shaddock'      => 'Pomelos',
    'Rakam'                          => 'Rakam',
    'Rambutan'                       => 'Rambutan',
    'Sala'                           => 'Sala',
    'Santol'                         => 'Santol',
    'Sapodilla'                      => 'Sapodilla',
    'Star gooseberry, jimbling'      => 'Star gooseberries',
    'Strawberry'                     => 'Strawberries',
    'Sugar Apple'                    => 'Sugar apples',
    'Tamarind'                       => 'Sweet tamarind',
    'Tangerine'                      => 'Tangerines',
    'Water melon'                    => 'Watermelons',
    'Wax jambu, Wax apple. Java apple' => 'Java apples',

    // Deliberately not mapped:
    // 'Acidless orange' - the catalogue lists "Oranges" generally, and the
    //   book's entry is specifically about the acidless cultivar. Attaching it
    //   would state something about the client's oranges that is not known.
];

$attached = $skipped = $specRows = 0;
$missing  = [];

foreach ($reference as $bookName => $specs) {
    if (!isset($MAP[$bookName])) {
        $skipped++;
        continue;
    }
    $productName = $MAP[$bookName];

    $product = Database::one(
        'SELECT id, name FROM products WHERE name = ? LIMIT 1', [$productName]);

    if (!$product) {
        // A rename in the catalogue would otherwise make this import quietly
        // do nothing at all, so it is reported rather than swallowed.
        $missing[] = $productName;
        continue;
    }

    if (!$dryRun) {
        // Only this group is cleared. Anything typed in the admin panel sits
        // under a different heading and is left exactly as it was.
        Database::run(
            'DELETE FROM product_specs WHERE product_id = ? AND spec_group = ?',
            [$product['id'], REFERENCE_GROUP]);

        // Start after the highest existing sort_order so the reference block
        // lands below the specs already on the product rather than interleaved.
        $base = (int) Database::scalar(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_specs WHERE product_id = ?',
            [$product['id']]);

        foreach ($specs as $i => [$name, $value]) {
            Database::run(
                'INSERT INTO product_specs
                    (product_id, spec_group, spec_name, spec_value, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$product['id'], REFERENCE_GROUP,
                 mb_substr($name, 0, 120), mb_substr($value, 0, 400), $base + $i]);
        }
    }

    $attached++;
    $specRows += count($specs);
    printf("  %-22s -> %s (%d rows)\n", $bookName, $product['name'], count($specs));
}

echo "\n";
printf("%s%d products matched, %d spec rows written.\n",
    $dryRun ? "DRY RUN - nothing written. " : '', $attached, $specRows);
printf("%d entries in the book have no mapping and were skipped.\n", $skipped);

if ($missing) {
    printf("\nWARNING - mapped but not found in the catalogue (renamed?):\n  %s\n",
        implode("\n  ", $missing));
}

echo "\nEvery row is quoted from the client's own reference PDF and grouped under\n";
echo "\"" . REFERENCE_GROUP . "\". No pack size, shelf life, MOQ, grade,\n";
echo "certification, HS code or price was written - none are stated in the book.\n";
