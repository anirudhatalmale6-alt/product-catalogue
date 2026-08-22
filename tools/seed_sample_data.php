<?php
/**
 * Loads the sample catalogue: 5 categories, 16 products, their specifications
 * and image rows.
 *
 *     php tools/seed_sample_data.php
 *
 * It clears the products, categories, images and specs tables first, so do not
 * run it once you have entered real data. Admin users and settings are left
 * alone.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$categories = [
    ['Power Tools',        'Corded and cordless machines for cutting, drilling and finishing.'],
    ['Hand Tools',         'Everyday workshop tools, forged and finished for daily use.'],
    ['Measuring & Test',   'Instruments for setting out, levelling and electrical testing.'],
    ['Safety Equipment',   'Certified personal protective equipment for site and workshop.'],
    ['Workshop Storage',   'Cases, cabinets and benches that keep a workspace in order.'],
];

$products = [
    // [category index, name, sku, brand, short, long, price, sale, status, qty, weight, featured, specs[]]
    [0, 'Brushless Cordless Drill Driver 18V', 'PT-DD1801', 'Halden',
     'Compact 18V brushless drill driver with a 13 mm metal chuck and 20-stage clutch.',
     "A brushless motor delivers more torque from the same battery and runs noticeably cooler under load, which is what makes the difference on a long day of repetitive fixings.\n\nThe 13 mm keyless chuck takes round and hex shanks, and the 20-stage clutch plus a dedicated drill mode means you can go from driving small screws in softwood to boring 38 mm holes without swapping tools.\n\nSupplied as a bare unit. Batteries and chargers from the same 18V platform fit without an adapter.",
     249.00, 219.00, 'in_stock', 34, 1450, 1,
     [
        ['Motor', 'Motor type', 'Brushless'],
        ['Motor', 'Voltage', '18 V DC'],
        ['Motor', 'No-load speed', '0-550 / 0-2000 rpm'],
        ['Performance', 'Max torque', '70 Nm'],
        ['Performance', 'Clutch settings', '20 + drill mode'],
        ['Performance', 'Chuck capacity', '1.5-13 mm keyless'],
        ['Performance', 'Max drilling - wood', '38 mm'],
        ['Performance', 'Max drilling - steel', '13 mm'],
        ['Physical', 'Length', '175 mm'],
        ['Physical', 'Weight (bare tool)', '1.45 kg'],
        ['Physical', 'Vibration level', '< 2.5 m/s²'],
        ['Supplied', 'In the box', 'Bare tool, belt clip, double-ended bit'],
        ['Supplied', 'Warranty', '3 years on registration'],
     ]],

    [0, '185 mm Circular Saw 1400W', 'PT-CS1400', 'Halden',
     'Mains circular saw with a cast aluminium base and 0-56° bevel.',
     "A 1400 W motor and a 185 mm blade give a 62 mm depth of cut at 90°, enough for a 2x4 in a single pass.\n\nThe base is cast rather than pressed, so it stays flat and the cut line stays true. Bevel and depth both lock with cam levers rather than wing nuts.",
     189.00, null, 'in_stock', 12, 3900, 0,
     [
        ['Motor', 'Input power', '1400 W'],
        ['Motor', 'No-load speed', '5200 rpm'],
        ['Cutting', 'Blade diameter', '185 mm'],
        ['Cutting', 'Bore', '20 mm'],
        ['Cutting', 'Depth of cut at 90°', '62 mm'],
        ['Cutting', 'Depth of cut at 45°', '43 mm'],
        ['Cutting', 'Bevel range', '0-56°'],
        ['Physical', 'Base material', 'Cast aluminium'],
        ['Physical', 'Weight', '3.9 kg'],
        ['Physical', 'Cable length', '4 m'],
        ['Supplied', 'In the box', '24T blade, rip fence, blade key'],
     ]],

    [0, 'Variable Speed Random Orbit Sander 125 mm', 'PT-OS125', 'Halden',
     'Low-vibration 125 mm orbital sander with dust extraction port.',
     "Six speed settings from 7000 to 12000 opm cover everything from paint removal to a final finish before oiling.\n\nThe counterbalanced head keeps vibration under 3 m/s², and the 32 mm port fits standard workshop extraction directly.",
     129.00, null, 'low_stock', 3, 1300, 0,
     [
        ['Motor', 'Input power', '350 W'],
        ['Motor', 'Orbits per minute', '7000-12000 opm'],
        ['Sanding', 'Pad diameter', '125 mm'],
        ['Sanding', 'Orbit diameter', '2.5 mm'],
        ['Sanding', 'Disc fixing', '8-hole hook and loop'],
        ['Extraction', 'Port diameter', '32 mm'],
        ['Extraction', 'Supplied bag', 'Micro-filter, 1.2 L'],
        ['Physical', 'Weight', '1.30 kg'],
        ['Physical', 'Vibration level', '2.9 m/s²'],
     ]],

    [0, 'SDS-Plus Rotary Hammer 800W', 'PT-RH0800', 'Corvent',
     'Three-mode SDS-plus hammer with vibration-damped side handle.',
     "Rotation, hammer-drilling and chisel-only in one tool, with a safety clutch that releases if the bit binds in reinforcement.\n\nThe side handle is isolated on rubber mounts, which is what makes overhead work with this class of tool tolerable.",
     279.00, null, 'preorder', null, 2800, 0,
     [
        ['Motor', 'Input power', '800 W'],
        ['Motor', 'Impact rate', '0-4400 bpm'],
        ['Performance', 'Impact energy', '2.7 J'],
        ['Performance', 'Modes', 'Drill / hammer drill / chisel'],
        ['Performance', 'Max drilling - concrete', '26 mm'],
        ['Performance', 'Tool holder', 'SDS-plus'],
        ['Physical', 'Weight', '2.80 kg'],
        ['Supplied', 'In the box', 'Side handle, depth stop, carry case'],
     ]],

    [1, 'Forged Claw Hammer 20 oz', 'HT-CH20', 'Brackwell',
     'One-piece forged head with a shock-absorbing hickory handle.',
     "The head and neck are forged from a single billet, so there is no join to loosen. A 20 oz head is the compromise most joiners settle on: heavy enough to sink a 100 mm nail in three strikes, light enough to use all day.\n\nHandle is American hickory, wedged and lacquered.",
     42.50, null, 'in_stock', 88, 780, 1,
     [
        ['Head', 'Weight', '20 oz / 567 g'],
        ['Head', 'Material', 'Drop-forged carbon steel'],
        ['Head', 'Finish', 'Polished face, black lacquer body'],
        ['Head', 'Hardness', '52-56 HRC'],
        ['Handle', 'Material', 'American hickory'],
        ['Handle', 'Length', '330 mm'],
        ['Handle', 'Fixing', 'Wedged and epoxy-set'],
        ['Physical', 'Total weight', '780 g'],
     ]],

    [1, 'Chrome Vanadium Socket Set - 94 Piece', 'HT-SS94', 'Brackwell',
     '94-piece metric socket set in a latching case, 1/4in and 1/2in drive.',
     "Sockets are chrome vanadium with a satin chrome finish, bi-hex so they grip the flats rather than the corners of a fastener.\n\nBoth ratchets are 72-tooth, which needs only a 5° swing - the difference between reaching a bolt and not.",
     164.00, 139.00, 'in_stock', 21, 6200, 1,
     [
        ['Contents', 'Pieces', '94'],
        ['Contents', 'Drive sizes', '1/4 in and 1/2 in'],
        ['Contents', 'Socket range 1/4 in', '4-14 mm'],
        ['Contents', 'Socket range 1/2 in', '10-32 mm'],
        ['Contents', 'Deep sockets', '10, 13, 17, 19 mm'],
        ['Contents', 'Bit types', 'Hex, Torx, Pozi, slotted'],
        ['Build', 'Material', 'Chrome vanadium steel'],
        ['Build', 'Ratchet teeth', '72 (5° swing)'],
        ['Build', 'Standard', 'DIN 3120 / ISO 1174'],
        ['Case', 'Dimensions', '440 x 320 x 75 mm'],
        ['Case', 'Weight (loaded)', '6.2 kg'],
     ]],

    [1, 'Adjustable Wrench 250 mm', 'HT-AW250', 'Brackwell',
     'Wide-opening 250 mm adjustable wrench with a hardened worm screw.',
     "Opens to 34 mm, which covers most plumbing and general fitting work. The scale is laser-etched into the body rather than printed, so it survives being kept in a bag with everything else.",
     28.90, null, 'in_stock', 46, 520, 0,
     [
        ['Dimensions', 'Overall length', '250 mm'],
        ['Dimensions', 'Jaw capacity', '0-34 mm'],
        ['Dimensions', 'Jaw thickness', '9 mm'],
        ['Build', 'Material', 'Forged chrome vanadium'],
        ['Build', 'Finish', 'Phosphated with lacquer'],
        ['Build', 'Standard', 'DIN 3117 Form A'],
        ['Physical', 'Weight', '520 g'],
     ]],

    [1, 'Wood Chisel Set - 6 Piece Bevel Edge', 'HT-CH06', 'Ferrow',
     'Six bevel-edge chisels, 6 to 32 mm, in a roll.',
     "Blades are chrome manganese steel hardened to 60 HRC and ground to a 25° primary bevel, ready to hone.\n\nThe handles take a mallet without mushrooming, and the canvas roll keeps the edges apart in a bag.",
     96.00, null, 'in_stock', 17, 1100, 0,
     [
        ['Contents', 'Sizes', '6, 10, 12, 18, 25, 32 mm'],
        ['Blade', 'Material', 'Chrome manganese steel'],
        ['Blade', 'Hardness', '60 HRC'],
        ['Blade', 'Primary bevel', '25°'],
        ['Handle', 'Material', 'Impact-resistant polypropylene'],
        ['Handle', 'Strike cap', 'Steel'],
        ['Storage', 'Supplied in', 'Canvas tool roll'],
        ['Physical', 'Weight (set)', '1.10 kg'],
     ]],

    [2, 'Self-Levelling Cross Line Laser', 'MT-LL02', 'Ferrow',
     'Green-beam cross line laser, self-levelling to ±4°, 30 m range.',
     "Green beams read roughly four times brighter than red to the human eye, which is what makes this usable in a room with the lights on.\n\nThe pendulum locks for transport - a detail that decides whether a laser survives its first year in a van.",
     215.00, null, 'in_stock', 9, 640, 1,
     [
        ['Accuracy', 'Levelling accuracy', '±0.3 mm/m'],
        ['Accuracy', 'Self-levelling range', '±4°'],
        ['Accuracy', 'Levelling time', '< 4 seconds'],
        ['Laser', 'Beam colour', 'Green, 510-530 nm'],
        ['Laser', 'Laser class', 'Class 2, < 1 mW'],
        ['Laser', 'Working range', '30 m (60 m with detector)'],
        ['Power', 'Batteries', '3 x AA'],
        ['Power', 'Run time', '12 hours continuous'],
        ['Build', 'Ingress protection', 'IP54'],
        ['Build', 'Tripod thread', '1/4 in and 5/8 in'],
        ['Physical', 'Weight', '640 g'],
     ]],

    [2, 'True RMS Digital Multimeter', 'MT-DM600', 'Corvent',
     'CAT III 600V true-RMS meter with a 6000-count backlit display.',
     "True RMS matters the moment you measure anything that is not a clean sine wave - dimmers, variable speed drives, most modern lighting.\n\nRated CAT III 600 V, with fused current inputs and a non-contact voltage detector built into the top of the case.",
     118.00, null, 'in_stock', 27, 380, 0,
     [
        ['Display', 'Count', '6000'],
        ['Display', 'Backlight', 'White LED'],
        ['Measurement', 'DC voltage', '600 V, ±0.5%'],
        ['Measurement', 'AC voltage', '600 V true RMS, ±1.0%'],
        ['Measurement', 'DC/AC current', '10 A'],
        ['Measurement', 'Resistance', '60 MΩ'],
        ['Measurement', 'Capacitance', '100 mF'],
        ['Measurement', 'Frequency', '10 MHz'],
        ['Measurement', 'Temperature', '-40 to 1000 °C (K-type)'],
        ['Safety', 'Category', 'CAT III 600 V / CAT II 1000 V'],
        ['Safety', 'Standard', 'IEC 61010-1'],
        ['Safety', 'Input protection', 'Fused 10 A / 600 mA'],
        ['Physical', 'Weight', '380 g'],
     ]],

    [2, 'Digital Vernier Caliper 150 mm', 'MT-VC150', 'Ferrow',
     'Stainless 150 mm caliper reading to 0.01 mm, metric and imperial.',
     "Hardened stainless jaws for outside, inside, depth and step measurement, with a zero-anywhere button for comparative work.\n\nComes in a fitted case with a spare cell.",
     54.00, 45.00, 'in_stock', 40, 260, 0,
     [
        ['Measurement', 'Range', '0-150 mm / 0-6 in'],
        ['Measurement', 'Resolution', '0.01 mm / 0.0005 in'],
        ['Measurement', 'Accuracy', '±0.02 mm'],
        ['Measurement', 'Repeatability', '0.01 mm'],
        ['Functions', 'Modes', 'Outside, inside, depth, step'],
        ['Functions', 'Zero', 'At any position'],
        ['Build', 'Material', 'Hardened stainless steel'],
        ['Power', 'Battery', 'SR44 (1 spare included)'],
        ['Physical', 'Weight', '260 g'],
     ]],

    [3, 'Safety Helmet with Ratchet Harness', 'SE-HH01', 'Vantor',
     'Vented EN 397 helmet with 6-point harness and 30 mm accessory slots.',
     "The ratchet adjusts one-handed while the helmet is on, and the harness is six-point rather than four, which spreads the load and stops the shell rocking.\n\nStandard 30 mm side slots accept ear defenders and visors.",
     34.00, null, 'in_stock', 120, 380, 0,
     [
        ['Certification', 'Standard', 'EN 397:2012 + A1:2012'],
        ['Certification', 'Optional tests', 'LD (lateral deformation), -30 °C'],
        ['Certification', 'Electrical', '440 V AC'],
        ['Build', 'Shell material', 'ABS'],
        ['Build', 'Harness', '6-point textile'],
        ['Build', 'Adjustment', 'Ratchet, 53-63 cm'],
        ['Build', 'Ventilation', '8 vents, closable'],
        ['Build', 'Accessory slots', '30 mm universal'],
        ['Physical', 'Weight', '380 g'],
        ['Physical', 'Shelf life', '5 years from date of manufacture'],
     ]],

    [3, 'Cut Level D Work Gloves', 'SE-GL05', 'Vantor',
     'HPPE-lined gloves with nitrile palm coating, EN 388 4X43D.',
     "Cut level D is the point where a glove stops being about comfort and starts being about sheet metal and glass.\n\nThe nitrile palm keeps grip on oily parts without turning the glove into a board - they still pass a touchscreen test.",
     14.50, null, 'in_stock', 340, 90, 0,
     [
        ['Certification', 'Standard', 'EN 388:2016'],
        ['Certification', 'Rating', '4X43D'],
        ['Certification', 'Cut resistance', 'Level D (ISO 13997)'],
        ['Certification', 'Abrasion', 'Level 4'],
        ['Build', 'Liner', 'HPPE / glass fibre, 13 gauge'],
        ['Build', 'Coating', 'Sandy nitrile, palm and fingertips'],
        ['Build', 'Cuff', 'Knitted, 75 mm'],
        ['Sizing', 'Sizes', '7 (S) to 11 (XXL)'],
        ['Physical', 'Weight (pair, size 9)', '90 g'],
     ]],

    [3, 'Electronic Ear Defenders SNR 31 dB', 'SE-ED31', 'Vantor',
     'Level-dependent ear defenders that cut impulse noise but pass speech.',
     "Microphones sample outside sound and cut anything above 82 dB in a few milliseconds, so a nail gun going off nearby is dealt with but the person talking to you is not.\n\nRuns about 180 hours on two AAA cells.",
     89.00, null, 'out_of_stock', 0, 310, 0,
     [
        ['Certification', 'Standard', 'EN 352-1 / EN 352-4'],
        ['Attenuation', 'SNR', '31 dB'],
        ['Attenuation', 'H / M / L', '34 / 29 / 21 dB'],
        ['Electronics', 'Cut-off level', '82 dB'],
        ['Electronics', 'Response time', '< 4 ms'],
        ['Electronics', 'Aux input', '3.5 mm'],
        ['Power', 'Batteries', '2 x AAA'],
        ['Power', 'Run time', '180 hours'],
        ['Physical', 'Weight', '310 g'],
     ]],

    [4, 'Stackable Tool Case System - 3 Module Set', 'WS-TC03', 'Corvent',
     'Three interlocking cases on a wheeled base, IP54 rated.',
     "The modules latch to each other rather than just sitting on top, so the stack stays together when the base is tipped onto its wheels and pulled up a kerb.\n\nSeals are IP54, which is rain on a site, not immersion.",
     279.00, 245.00, 'in_stock', 15, 14200, 1,
     [
        ['Contents', 'Modules', '3 (deep, medium, organiser)'],
        ['Capacity', 'Total volume', '84 L'],
        ['Capacity', 'Max load per module', '25 kg'],
        ['Capacity', 'Max stack load', '75 kg'],
        ['Build', 'Material', 'Impact-resistant polypropylene'],
        ['Build', 'Ingress protection', 'IP54'],
        ['Build', 'Wheels', '150 mm, ball bearing'],
        ['Build', 'Handle', 'Two-stage telescopic aluminium'],
        ['Dimensions', 'Stack (W x D x H)', '590 x 390 x 930 mm'],
        ['Physical', 'Weight (empty)', '14.2 kg'],
     ]],

    [4, 'Steel Workbench 1500 mm with Vice', 'WS-WB15', 'Corvent',
     'Welded steel bench, 1500 mm hardwood top, 750 kg rated.',
     "The frame is welded rather than bolted, so it does not develop a wobble after a year of hammering on it. The 40 mm beech top is oiled and can be sanded back.\n\nA 150 mm quick-release vice is fitted at the left end as standard.",
     749.00, null, 'discontinued', 1, 68000, 0,
     [
        ['Dimensions', 'Length', '1500 mm'],
        ['Dimensions', 'Depth', '750 mm'],
        ['Dimensions', 'Working height', '900 mm'],
        ['Capacity', 'Uniformly distributed load', '750 kg'],
        ['Build', 'Frame', 'Welded 50 x 50 x 3 mm steel'],
        ['Build', 'Finish', 'Powder-coated RAL 7016'],
        ['Build', 'Worktop', '40 mm oiled beech'],
        ['Vice', 'Jaw width', '150 mm'],
        ['Vice', 'Opening', '200 mm'],
        ['Physical', 'Weight', '68 kg'],
        ['Delivery', 'Assembly', 'Legs bolt to frame, 20 minutes'],
     ]],
];

// ---------------------------------------------------------------------------

Database::run('SET FOREIGN_KEY_CHECKS = 0');
foreach (['product_specs', 'product_images', 'products', 'categories'] as $t) {
    Database::run("TRUNCATE TABLE {$t}");
}
Database::run('SET FOREIGN_KEY_CHECKS = 1');

$catIds = [];
foreach ($categories as $i => [$name, $description]) {
    $catIds[$i] = Database::insert(
        'INSERT INTO categories (name, slug, description, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)',
        [$name, slugify($name), $description, $i * 10]);
}

$imageCount = 0;
foreach ($products as $n => $p) {
    [$ci, $name, $sku, $brand, $short, $long, $price, $sale, $status, $qty, $weight, $featured, $specs] = $p;

    $productId = Database::insert(
        'INSERT INTO products
            (category_id, sku, name, slug, short_description, description, price, sale_price,
             stock_status, stock_qty, brand, weight_grams, is_active, is_featured, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
        [$catIds[$ci], $sku, $name, slugify($name), $short, $long, $price, $sale,
         $status, $qty, $brand, $weight, $featured, $n]);

    foreach ($specs as $i => [$group, $specName, $specValue]) {
        Database::run(
            'INSERT INTO product_specs (product_id, spec_group, spec_name, spec_value, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$productId, $group, $specName, $specValue, $i]);
    }

    // Three views per product. The files themselves are drawn by
    // tools/generate_placeholder_images.php.
    $views = ['Main view', 'Detail view', 'In use'];
    foreach ($views as $i => $alt) {
        $file = sprintf('products/sample/%s-%d.jpg', slugify($name), $i + 1);
        Database::run(
            'INSERT INTO product_images (product_id, file_path, alt_text, is_primary, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$productId, $file, $name . ' - ' . $alt, $i === 0 ? 1 : 0, $i]);
        $imageCount++;
    }
}

printf("Seeded %d categories, %d products, %d image rows.\n",
    count($catIds), count($products), $imageCount);
echo "Now run:  php tools/generate_placeholder_images.php\n";
