<?php
/**
 * Generates the studio-style placeholder images used by the sample data.
 *
 *     php tools/generate_placeholder_images.php
 *
 * You will replace these with real photographs - they exist so the catalogue
 * has something to show while the structure is being reviewed. Nothing in the
 * application depends on this script.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

const FONT_BOLD    = '/usr/share/fonts/truetype/lato/Lato-Bold.ttf';
const FONT_REGULAR = '/usr/share/fonts/truetype/lato/Lato-Regular.ttf';

function pick_font(string $preferred, string $fallback): string
{
    return is_file($preferred) ? $preferred : $fallback;
}

$fontBold = pick_font(FONT_BOLD, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
$fontReg  = pick_font(FONT_REGULAR, '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf');

/** Muted studio palettes, one per category index. */
$PALETTES = [
    ['bg' => [0xF3, 0xF0, 0xEB], 'bg2' => [0xE4, 0xDE, 0xD5], 'obj' => [0x8A, 0x93, 0x99], 'accent' => [0xA8, 0x45, 0x1C]],
    ['bg' => [0xF1, 0xF2, 0xF0], 'bg2' => [0xDF, 0xE2, 0xDE], 'obj' => [0x6F, 0x7C, 0x74], 'accent' => [0x2F, 0x6B, 0x53]],
    ['bg' => [0xF2, 0xF1, 0xEE], 'bg2' => [0xE1, 0xDF, 0xD8], 'obj' => [0x77, 0x7F, 0x8C], 'accent' => [0x2A, 0x5D, 0x9E]],
    ['bg' => [0xF5, 0xF1, 0xEB], 'bg2' => [0xE8, 0xDF, 0xD2], 'obj' => [0x94, 0x88, 0x78], 'accent' => [0xA3, 0x69, 0x0C]],
    ['bg' => [0xF1, 0xF0, 0xF2], 'bg2' => [0xDD, 0xDB, 0xE1], 'obj' => [0x7C, 0x78, 0x88], 'accent' => [0x5B, 0x3E, 0x8E]],
];

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

/** Word-wrapped, centred TTF text. Returns the y after the last line. */
function draw_centred_text($im, string $text, string $font, float $size, int $colour,
                           int $centreX, int $y, int $maxWidth, float $lineGap = 1.35): int
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
    foreach ($lines as $line) {
        $box = imagettfbbox($size, 0, $font, $line);
        $w = $box[2] - $box[0];
        imagettftext($im, $size, 0, (int) ($centreX - $w / 2), $y, $colour, $font, $line);
        $y += (int) round($size * $lineGap);
    }
    return $y;
}

/** Letter-spaced small-caps style line. */
function draw_tracked_text($im, string $text, string $font, float $size, int $colour,
                           int $centreX, int $y, float $tracking = 3.0): void
{
    $text = mb_strtoupper($text);
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = 0;
    foreach ($chars as $ch) {
        $b = imagettfbbox($size, 0, $font, $ch);
        $total += ($b[2] - $b[0]) + $tracking;
    }
    $x = $centreX - $total / 2;
    foreach ($chars as $ch) {
        imagettftext($im, $size, 0, (int) $x, $y, $colour, $font, $ch);
        $b = imagettfbbox($size, 0, $font, $ch);
        $x += ($b[2] - $b[0]) + $tracking;
    }
}

/**
 * One placeholder image.
 * $variant shifts the composition so the gallery views differ from each other.
 */
function make_image(string $absPath, string $name, string $brand, array $palette, int $variant): void
{
    global $fontBold, $fontReg;

    $S = 1000;
    $im = imagecreatetruecolor($S, $S);
    imageantialias($im, true);

    // --- Background: soft vertical gradient with a warm centre -------------
    for ($y = 0; $y < $S; $y++) {
        $t = $y / $S;
        // Lighter at the top third, deeper towards the base.
        $rgb = mix($palette['bg'], $palette['bg2'], pow($t, 1.4));
        $c = alloc($im, $rgb);
        imageline($im, 0, $y, $S, $y, $c);
        imagecolordeallocate($im, $c);
    }

    $cx = (int) ($S / 2);
    $cy = (int) ($S * 0.44) + ($variant * 8);

    // --- Contact shadow ----------------------------------------------------
    $shadow = mix($palette['bg2'], [0, 0, 0], 0.10);
    for ($i = 10; $i > 0; $i--) {
        $c = alloc($im, $shadow, (int) (118 - $i * 2));
        imagefilledellipse($im, $cx, (int) ($S * 0.70), (int) (430 + $i * 16), (int) (46 + $i * 5), $c);
    }

    // --- Composition -------------------------------------------------------
    $obj    = $palette['obj'];
    $objHi  = mix($obj, [255, 255, 255], 0.30);
    $objLo  = mix($obj, [0, 0, 0], 0.22);
    $accent = $palette['accent'];

    $cObj   = alloc($im, $obj);
    $cObjHi = alloc($im, $objHi);
    $cObjLo = alloc($im, $objLo);
    $cAcc   = alloc($im, $accent);

    switch ($variant % 3) {
        case 0:
            // Column + disc
            imagefilledrectangle($im, $cx - 150, $cy - 190, $cx + 30, $cy + 170, $cObj);
            imagefilledrectangle($im, $cx - 150, $cy - 190, $cx - 96, $cy + 170, $cObjHi);
            imagefilledrectangle($im, $cx - 6,  $cy - 190, $cx + 30, $cy + 170, $cObjLo);
            imagefilledellipse($im, $cx + 118, $cy + 60, 240, 240, $cObjHi);
            imagefilledellipse($im, $cx + 118, $cy + 60, 108, 108, $cAcc);
            imagefilledrectangle($im, $cx - 150, $cy + 128, $cx + 30, $cy + 170, $cAcc);
            break;

        case 1:
            // Stacked bars, offset
            imagefilledrectangle($im, $cx - 230, $cy - 40,  $cx + 110, $cy + 34,  $cObj);
            imagefilledrectangle($im, $cx - 180, $cy + 54,  $cx + 210, $cy + 128, $cObjLo);
            imagefilledrectangle($im, $cx - 120, $cy - 200, $cx + 60,  $cy - 126, $cObjHi);
            imagefilledellipse($im, $cx + 152, $cy - 3, 92, 92, $cAcc);
            break;

        default:
            // Ring and blade
            imagefilledellipse($im, $cx, $cy, 340, 340, $cObjHi);
            imagefilledellipse($im, $cx, $cy, 196, 196, alloc($im, $palette['bg']));
            imagefilledrectangle($im, $cx - 28, $cy - 250, $cx + 28, $cy + 250, $cObj);
            imagefilledrectangle($im, $cx - 28, $cy + 150, $cx + 28, $cy + 250, $cAcc);
            imagefilledellipse($im, $cx, $cy, 60, 60, $cObjLo);
            break;
    }

    // --- Text --------------------------------------------------------------
    $cInk   = alloc($im, [0x24, 0x28, 0x2C]);
    $cFaint = alloc($im, [0x86, 0x81, 0x7A]);

    if ($brand !== '') {
        draw_tracked_text($im, $brand, $fontBold, 19, $cFaint, $cx, (int) ($S * 0.815), 4.0);
    }
    draw_centred_text($im, $name, $fontBold, 34, $cInk, $cx, (int) ($S * 0.885), 760, 1.28);

    imagejpeg($im, $absPath, 90);
    imagedestroy($im);
}

// ---------------------------------------------------------------------------
// Walk the products that have image rows pointing at files we have not made
// yet, and render one image per row.
// ---------------------------------------------------------------------------

$rows = Database::all(
    'SELECT i.id, i.file_path, i.sort_order, p.name, p.brand, p.category_id
       FROM product_images i
       JOIN products p ON p.id = i.product_id
      ORDER BY i.product_id, i.sort_order');

$catIndex = [];
foreach (Database::all('SELECT id FROM categories ORDER BY sort_order, id') as $n => $c) {
    $catIndex[(int) $c['id']] = $n;
}

$made = 0;
foreach ($rows as $r) {
    $abs = UPLOAD_DIR . '/' . $r['file_path'];
    $dir = dirname($abs);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pi = $catIndex[(int) $r['category_id']] ?? 0;
    $palette = $PALETTES[$pi % count($PALETTES)];
    make_image($abs, $r['name'], (string) ($r['brand'] ?? ''), $palette, (int) $r['sort_order']);
    $made++;
}

echo "Generated {$made} placeholder images under public/uploads/.\n";
