<?php
/**
 * Draws the stand-in product images.
 *
 *     php tools/generate_placeholder_images.php
 *
 * These are placeholders, and they are drawn to look like placeholders: a dark
 * brand plate with the product name and its category. They are not pretending
 * to be photographs of produce, because a generated picture of a mango that a
 * buyer might take for the real thing is worse than an obvious placeholder.
 *
 * Replace them by uploading real photographs against each product in the admin
 * panel - the moment a product has an uploaded image, the placeholder stops
 * being used. Nothing in the application depends on this script.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

// Brand colours, from the Disruptive Sourcing standards.
const BLACK   = [0x0E, 0x0E, 0x0E];
const CARBON  = [0x2B, 0x2B, 0x2B];
const STEEL   = [0x6A, 0x6A, 0x6A];
const WHITE   = [0xF5, 0xF5, 0xF5];
const RED     = [0xC8, 0x10, 0x2E];

function pick_font(array $candidates): string
{
    foreach ($candidates as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    throw new RuntimeException('No usable TrueType font found.');
}

// Roboto is the brand face but is not installed system-wide on every box, so
// the nearest metric-compatible fallbacks are listed behind it.
$fontBold = pick_font([
    '/usr/share/fonts/truetype/roboto/unhinted/RobotoTTF/Roboto-Bold.ttf',
    '/usr/share/fonts/truetype/roboto/Roboto-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
]);
$fontReg = pick_font([
    '/usr/share/fonts/truetype/roboto/unhinted/RobotoTTF/Roboto-Regular.ttf',
    '/usr/share/fonts/truetype/roboto/Roboto-Regular.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
]);

function alloc($im, array $rgb, int $alpha = 0)
{
    return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $alpha);
}

function mix(array $a, array $b, float $t): array
{
    return [
        (int) round($a[0] + ($b[0] - $a[0]) * $t),
        (int) round($a[1] + ($b[1] - $a[1]) * $t),
        (int) round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

/** Word-wrapped centred text. Returns the lines it used. */
function wrap_lines(string $text, string $font, float $size, int $maxWidth): array
{
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $current = '';
    foreach ($words as $w) {
        $try = $current === '' ? $w : $current . ' ' . $w;
        $box = imagettfbbox($size, 0, $font, $try);
        if (($box[2] - $box[0]) > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $w;
        } else {
            $current = $try;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }
    return $lines;
}

function text_width(string $s, string $font, float $size): int
{
    $b = imagettfbbox($size, 0, $font, $s);
    return $b[2] - $b[0];
}

/** Letter-spaced upper-case line, centred on $centreX. */
function draw_tracked($im, string $text, string $font, float $size, int $colour,
                      int $centreX, int $y, float $tracking = 4.0): void
{
    $chars = preg_split('//u', mb_strtoupper($text), -1, PREG_SPLIT_NO_EMPTY);
    $total = 0;
    foreach ($chars as $ch) {
        $total += text_width($ch, $font, $size) + $tracking;
    }
    $x = $centreX - $total / 2;
    foreach ($chars as $ch) {
        imagettftext($im, $size, 0, (int) $x, $y, $colour, $font, $ch);
        $x += text_width($ch, $font, $size) + $tracking;
    }
}

/**
 * One placeholder plate.
 * $variant shifts the frame so the extra gallery views differ from the first.
 */
function make_image(string $absPath, string $name, string $category, int $variant): void
{
    global $fontBold, $fontReg;

    $S = 1000;
    $im = imagecreatetruecolor($S, $S);
    imageantialias($im, true);

    // Background: Industrial Black lifted very slightly towards Carbon at the
    // base, so the plate has some depth without turning into a gradient.
    for ($y = 0; $y < $S; $y++) {
        $rgb = mix(BLACK, [0x1C, 0x1C, 0x1C], pow($y / $S, 1.6));
        $c = alloc($im, $rgb);
        imageline($im, 0, $y, $S, $y, $c);
        imagecolordeallocate($im, $c);
    }

    $cWhite  = alloc($im, WHITE);
    $cSteel  = alloc($im, STEEL);
    $cCarbon = alloc($im, CARBON);
    $cRed    = alloc($im, RED);

    // A thin carbon grid, the "grid-calibrated" note from the brand board.
    for ($g = 100; $g < $S; $g += 100) {
        imageline($im, $g, 0, $g, $S, $cCarbon);
        imageline($im, 0, $g, $S, $g, $cCarbon);
    }

    // Corner brackets, offset per variant.
    $pad = 70 + $variant * 22;
    $len = 90;
    $t   = 4;
    foreach ([[0, 0, 1, 1], [1, 0, -1, 1], [0, 1, 1, -1], [1, 1, -1, -1]] as [$fx, $fy, $dx, $dy]) {
        $x = $fx ? $S - $pad : $pad;
        $y = $fy ? $S - $pad : $pad;
        imagefilledrectangle($im, min($x, $x + $dx * $len), $y - $t / 2,
                                  max($x, $x + $dx * $len), $y + $t / 2, $cSteel);
        imagefilledrectangle($im, $x - $t / 2, min($y, $y + $dy * $len),
                                  $x + $t / 2, max($y, $y + $dy * $len), $cSteel);
    }

    // Red rule above the name, the masthead motif.
    $cx = (int) ($S / 2);
    imagefilledrectangle($im, $cx - 90, 392, $cx + 90, 398, $cRed);

    // Category, tracked and small.
    draw_tracked($im, $category, $fontBold, 20, $cSteel, $cx, 350, 5.0);

    // Product name, wrapped, sitting under the rule.
    $size  = 44;
    $lines = wrap_lines($name, $fontBold, $size, 720);
    // Long medical descriptions need a smaller face or they run to five lines.
    while (count($lines) > 3 && $size > 26) {
        $size -= 4;
        $lines = wrap_lines($name, $fontBold, $size, 720);
    }
    $lineHeight = (int) round($size * 1.32);
    $y = 470;
    foreach ($lines as $line) {
        imagettftext($im, $size, 0, (int) ($cx - text_width($line, $fontBold, $size) / 2),
                     $y, $cWhite, $fontBold, $line);
        $y += $lineHeight;
    }

    // Honest footer: this is not a photograph.
    draw_tracked($im, 'Image to follow', $fontReg, 17, $cSteel, $cx, $S - 120, 4.0);

    imagejpeg($im, $absPath, 88);
    imagedestroy($im);
}

// ---------------------------------------------------------------------------

$rows = Database::all(
    'SELECT i.file_path, i.sort_order, p.name, c.name AS category_name
       FROM product_images i
       JOIN products p   ON p.id = i.product_id
       LEFT JOIN categories c ON c.id = p.category_id
      ORDER BY i.product_id, i.sort_order');

$made = 0;
foreach ($rows as $r) {
    $abs = UPLOAD_DIR . '/' . $r['file_path'];
    $dir = dirname($abs);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    make_image($abs, $r['name'], (string) ($r['category_name'] ?? ''), (int) $r['sort_order']);
    $made++;
}

echo "Generated {$made} placeholder images under public/uploads/.\n";
