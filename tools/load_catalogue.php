<?php
/**
 * Loads the Disruptive Sourcing catalogue from tools/data/catalogue_items.php.
 *
 *     php tools/load_catalogue.php
 *
 * Clears products, categories, origins, images and specs, then rebuilds them.
 * Admin users and settings are left alone. Do not run it once real edits have
 * been made through the admin panel - it would discard them.
 *
 * WHAT THIS SCRIPT WILL AND WILL NOT WRITE
 *
 * Every product is loaded with no price. That is not an omission: on an export
 * catalogue a figure depends on volume and incoterm, so the item shows
 * "Price on request" until someone types one in.
 *
 * Specifications are only written when the item's own name states them - the
 * form ("Frozen", "Freeze-dried", "Vacuum-fried"), and for the medical lines
 * the brand, model and protection level that appear in the name itself.
 * Nothing invents a shelf life, a pack size, a minimum order or a
 * certification. Those are commercial claims and they get typed in by someone
 * who knows the answer, helped along by the per-category heading templates
 * below.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$ITEMS = require __DIR__ . '/data/catalogue_items.php';

// ---------------------------------------------------------------------------
// Categories: description and the specification headings that belong on them.
// The headings are suggestions offered on the product form, never values.
// ---------------------------------------------------------------------------
$CATEGORIES = [
    'Fresh Fruit' => [
        'Fresh tropical and temperate fruit, packed for wholesale and export.',
        "Product|Variety\nProduct|Grade\nProduct|Size or count\nPacking|Pack format\nPacking|Pack sizes\nLogistics|Minimum order\nLogistics|Storage temperature\nLogistics|Shelf life\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code",
    ],
    'Vegetables, Herbs and Spices' => [
        'Fresh vegetables, culinary herbs and whole spices.',
        "Product|Variety\nProduct|Grade\nPacking|Pack format\nPacking|Pack sizes\nLogistics|Minimum order\nLogistics|Storage temperature\nLogistics|Shelf life\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code",
    ],
    'Frozen, Dried and Preserved Products' => [
        'Frozen, freeze-dried, dried, canned and prepared lines.',
        "Product|Form\nProduct|Cut or style\nProduct|Ingredients\nPacking|Pack format\nPacking|Pack sizes\nPacking|Net weight\nLogistics|Minimum order\nLogistics|Storage temperature\nLogistics|Shelf life\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code",
    ],
    'Coconut Products' => [
        'The full coconut range, from water and meat through to oils, sugars and snacks.',
        "Product|Form\nProduct|Ingredients\nProduct|Organic\nPacking|Pack format\nPacking|Pack sizes\nPacking|Net weight\nLogistics|Minimum order\nLogistics|Shelf life\nLogistics|Storage\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code\nOEM|Private label available",
    ],
    'Juices and Beverages' => [
        'Single-fruit and blended juices, concentrates, smoothies and drinks.',
        "Product|Form\nProduct|Juice content\nProduct|Ingredients\nPacking|Pack format\nPacking|Fill volume\nPacking|Units per case\nLogistics|Minimum order\nLogistics|Shelf life\nLogistics|Storage\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code\nOEM|Private label available",
    ],
    'Processed Foods and Snacks' => [
        'Prepared foods, snacks, rice lines, spreads and seasonings.',
        "Product|Form\nProduct|Ingredients\nProduct|Allergens\nPacking|Pack format\nPacking|Net weight\nPacking|Units per case\nLogistics|Minimum order\nLogistics|Shelf life\nLogistics|Storage\nLogistics|Incoterms\nCompliance|Certifications\nCompliance|HS code\nOEM|Private label available",
    ],
    'Commodities' => [
        'Bulk commodities and raw materials.',
        "Product|Specification\nProduct|Grade\nProduct|Purity or content\nLogistics|Minimum order\nLogistics|Packing\nLogistics|Loading port\nLogistics|Incoterms\nCompliance|Inspection\nCompliance|HS code",
    ],
    'Medical Products' => [
        'Masks, respirators and protective apparel.',
        "Product|Brand\nProduct|Model\nProduct|Protection level\nProduct|Fit\nProduct|Layers\nPacking|Units per box\nPacking|Boxes per case\nLogistics|Minimum order\nLogistics|Shelf life\nCompliance|Certifications\nCompliance|Regulatory approvals\nCompliance|HS code",
    ],
];

// ---------------------------------------------------------------------------
// Thai origin. Every one of these appears by name in the supplier list the
// client sent - "MATRIX SOUTH EAST ASIA CO., LIMITED, THAILAND - New Items
// 2K25" - across its Fresh, Juices, Frozen, Canned, Dried, Freeze Dried,
// Processed and Agricultural sheets. Anything not on that list is left with no
// origin rather than guessed at, and shows up under the "Not specified" filter
// so it can be filled in.
// ---------------------------------------------------------------------------
$THAI_EVIDENCED = [
    'durian', 'longan', 'longkong', 'mango', 'mangosteen', 'coconut', 'rambutan',
    'pomelo', 'pineapple', 'banana', 'lychee', 'tamarind', 'chili', 'chilli',
    'ginger', 'shallot', 'strawberry', 'strawberries', 'roselle', 'jackfruit',
    'lime', 'amaranth', 'papaya', 'asparagus', 'baby corn', 'passion fruit',
    'lemongrass', 'okra', 'yardlong', 'sugar apple', 'turmeric', 'galangal',
    'potato', 'potatoes', 'carrot', 'tomato', 'pumpkin', 'corn', 'cassava',
    'mulberr', 'bamboo', 'guava', 'kiwi', 'dragon fruit', 'cantaloupe',
    'edamame', 'garlic', 'onion', 'mung bean', 'aloe vera', 'melon seed',
    'cocoa', 'honey', 'rice', 'tofu', "bird's nest", 'green pea', 'orange',
    'sweet potato', 'tropical fruit', 'fruit puree', 'fruit powder',
    'frozen fruit', 'frozen vegetable', 'frozen herb', 'freeze-dried fruit',
    'freeze-dried vegetable', 'dried fruit', 'dried vegetable', 'canned fruit',
    'canned vegetable', 'noodles', 'granola', 'cereal', 'spice paste',
    'seasoning', 'jelly', 'jam', 'tart', 'spread', 'cracker', 'nuts',
];

// Categories whose origin is NOT inferred from the Thai supplier list.
$NO_INFERRED_ORIGIN = ['Commodities', 'Medical Products'];

// Origin stated in the item's own name, which beats any inference.
$ORIGIN_IN_NAME = [
    'Thai cocoa beans'     => 'Thailand',
    'Liberian cocoa beans' => null,      // Liberia is not one of the five origins
];

// ---------------------------------------------------------------------------
// Brands stated in the medical item names. Read off the names, not looked up.
// ---------------------------------------------------------------------------
$MEDICAL_BRANDS = [
    'Canadasmasq'         => 'Canadasmasq',
    'Primed'              => 'Primed',
    'JY Care'             => 'JY Care',
    'VMedCare'            => 'VMedCare',
    'Technologist Choice' => 'Technologist Choice',
    'SafeMask'            => 'SafeMask',
    'Halyard'             => 'Halyard',
    '3M'                  => '3M',
];

/**
 * Specification rows that the item's own name states outright.
 * Returns [[group, name, value], ...] - often empty, and that is correct.
 */
function derived_specs(string $category, string $name): array
{
    $n = mb_strtolower($name);
    $out = [];

    $form = null;
    if ($category === 'Fresh Fruit' || $category === 'Vegetables, Herbs and Spices') {
        $form = 'Fresh';
    }
    // Order matters: "freeze-dried" must be tested before "dried", and
    // "vacuum-fried" before "fried", or the broader word wins.
    foreach ([
        'freeze-dried'   => 'Freeze-dried',
        'vacuum-fried'   => 'Vacuum-fried',
        'frozen'         => 'Frozen',
        'canned'         => 'Canned',
        'dried'          => 'Dried',
        'concentrate'    => 'Concentrate',
        'smoothie'       => 'Smoothie',
        'juice'          => 'Juice',
        'powder'         => 'Powder',
        'puree'          => 'Puree',
        'oil'            => 'Oil',
        'peeled'         => 'Peeled',
        'minced'         => 'Minced',
        'ready-to-eat'   => 'Ready to eat',
        'crispy'         => 'Crispy',
    ] as $needle => $label) {
        if (str_contains($n, $needle)) {
            $form = $label;
            break;
        }
    }
    if ($form !== null) {
        $out[] = ['Product', 'Form', $form];
    }

    if ($category === 'Medical Products') {
        global $MEDICAL_BRANDS;
        foreach ($MEDICAL_BRANDS as $needle => $brand) {
            if (stripos($name, $needle) === 0 || str_contains($name, $needle)) {
                $out[] = ['Product', 'Brand', $brand];
                break;
            }
        }
        if (preg_match('/model ([A-Za-z0-9\-\+]+)/i', $name, $m)) {
            $out[] = ['Product', 'Model', $m[1]];
        }
        if (preg_match('/Level (\d)/i', $name, $m)) {
            $out[] = ['Product', 'Protection level', 'ASTM Level ' . $m[1]];
        }
        if (stripos($name, 'N95') !== false) {
            $out[] = ['Product', 'Protection level', 'N95'];
        } elseif (stripos($name, 'KN95') !== false) {
            $out[] = ['Product', 'Protection level', 'KN95'];
        }
        if (stripos($name, 'earloop') !== false) {
            $out[] = ['Product', 'Fit', 'Earloop'];
        }
        if (stripos($name, 'three-ply') !== false) {
            $out[] = ['Product', 'Layers', 'Three-ply'];
        }
        foreach (['regular' => 'Regular', 'medium' => 'Medium', 'small' => 'Small'] as $needle => $size) {
            if (preg_match('/,\s*' . $needle . '$/i', $name)) {
                $out[] = ['Product', 'Size', $size];
                break;
            }
        }
    }

    return $out;
}

/** The origin id for a product, or null when nothing in the source says. */
function derive_origin(string $category, string $name, array $originIds): ?int
{
    global $THAI_EVIDENCED, $NO_INFERRED_ORIGIN, $ORIGIN_IN_NAME;

    if (array_key_exists($name, $ORIGIN_IN_NAME)) {
        $stated = $ORIGIN_IN_NAME[$name];
        return $stated !== null ? ($originIds[$stated] ?? null) : null;
    }
    if (in_array($category, $NO_INFERRED_ORIGIN, true)) {
        return null;
    }
    $n = mb_strtolower($name);
    foreach ($THAI_EVIDENCED as $needle) {
        if (str_contains($n, $needle)) {
            return $originIds['Thailand'] ?? null;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------

Database::run('SET FOREIGN_KEY_CHECKS = 0');
foreach (['product_specs', 'product_images', 'products', 'categories', 'origins'] as $t) {
    Database::run("TRUNCATE TABLE {$t}");
}
Database::run('SET FOREIGN_KEY_CHECKS = 1');

$originIds = [];
foreach ([
    ['Canada', 'CA'], ['United States', 'US'], ['Mexico', 'MX'],
    ['Thailand', 'TH'], ['Vietnam', 'VN'],
] as $i => [$oName, $code]) {
    $originIds[$oName] = Database::insert(
        'INSERT INTO origins (name, slug, code, sort_order, is_active) VALUES (?, ?, ?, ?, 1)',
        [$oName, slugify($oName), $code, ($i + 1) * 10]);
}

$catIds = [];
$i = 0;
foreach ($ITEMS as $catName => $_) {
    [$description, $template] = $CATEGORIES[$catName] ?? ['', null];
    $catIds[$catName] = Database::insert(
        'INSERT INTO categories (name, slug, description, spec_template, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, 1)',
        [$catName, slugify($catName), $description, $template, (++$i) * 10]);
}

$sortOrder  = 0;
$specCount  = 0;
$imageCount = 0;
$withOrigin = 0;
$productCount = 0;

// A handful of items carry extra gallery views so the image gallery and its
// thumbnail strip can be seen working. Everything else gets one image.
$MULTI_VIEW = ['Durian', 'Mangoes', 'Coconut water', 'Dragon fruit',
               'Coconut oil', 'Mango sticky rice', 'Pineapples',
               '3M N95 respirators, model 8210'];

foreach ($ITEMS as $catName => $items) {
    foreach ($items as [$name, $short]) {
        $originId = derive_origin($catName, $name, $originIds);
        if ($originId !== null) {
            $withOrigin++;
        }

        $brand = null;
        if ($catName === 'Medical Products') {
            foreach ($MEDICAL_BRANDS as $needle => $b) {
                if (str_contains($name, $needle)) { $brand = $b; break; }
            }
        }

        $productId = Database::insert(
            'INSERT INTO products
                (category_id, origin_id, name, slug, short_description, price, sale_price,
                 stock_status, brand, is_active, is_featured, sort_order)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?, 1, ?, ?)',
            [$catIds[$catName], $originId, $name, unique_slug('products', slugify($name)),
             $short, 'made_to_order', $brand,
             in_array($name, $MULTI_VIEW, true) ? 1 : 0, $sortOrder++]);
        $productCount++;

        foreach (derived_specs($catName, $name) as $k => [$group, $specName, $specValue]) {
            Database::run(
                'INSERT INTO product_specs (product_id, spec_group, spec_name, spec_value, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$productId, $group, $specName, $specValue, $k]);
            $specCount++;
        }

        $views = in_array($name, $MULTI_VIEW, true)
            ? ['Main view', 'Detail view', 'Packed']
            : ['Main view'];
        foreach ($views as $k => $alt) {
            Database::run(
                'INSERT INTO product_images (product_id, file_path, alt_text, is_primary, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$productId,
                 sprintf('products/catalogue/%s-%d.jpg', slugify($name), $k + 1),
                 $name . ' - ' . $alt, $k === 0 ? 1 : 0, $k]);
            $imageCount++;
        }
    }
}

printf("Loaded %d categories, %d origins, %d products, %d spec rows, %d image rows.\n",
    count($catIds), count($originIds), $productCount, $specCount, $imageCount);
printf("Origin set from the supplier list on %d of %d products; %d left as 'not specified'.\n",
    $withOrigin, $productCount, $productCount - $withOrigin);
echo "Every product loaded with no price - they show 'Price on request'.\n";
echo "Now run:  php tools/generate_placeholder_images.php\n";
