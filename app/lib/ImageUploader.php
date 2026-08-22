<?php
/**
 * Handles product image uploads.
 *
 * Every uploaded file is re-encoded through GD before it is saved. That does
 * two things at once: it strips anything hidden inside the file (a PHP payload
 * appended to a valid JPEG is a classic trick) and it downscales oversized
 * camera shots so the catalogue stays fast.
 *
 * Filenames are generated, never taken from the client, so a name like
 * "../../index.php" or "shell.php.jpg" cannot do anything.
 */
class ImageUploader
{
    /** mime => extension we are willing to accept */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Takes one entry from $_FILES (already un-bundled) and returns the path
     * relative to public/uploads, e.g. "products/2026/a1b2c3.jpg".
     *
     * @throws RuntimeException with a message safe to show the admin user.
     */
    public static function store(array $file, string $subdir = 'products'): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Malformed upload.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('That image is larger than the server allows.');
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file was sent.');
            default:
                throw new RuntimeException('Upload failed (code ' . (int) $file['error'] . ').');
        }

        $maxBytes = (int) config('uploads.max_bytes', 6 * 1024 * 1024);
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException(sprintf(
                'Image is %.1f MB - the limit is %.1f MB.',
                $file['size'] / 1048576, $maxBytes / 1048576
            ));
        }

        // is_uploaded_file makes sure this really came through the POST and is
        // not some other path an attacker persuaded us to read.
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload could not be verified.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Only JPG, PNG, GIF and WebP images are accepted.');
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new RuntimeException('That file is not a readable image.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > 60000000) {
            throw new RuntimeException('Image dimensions are not usable.');
        }

        $src = self::loadImage($file['tmp_name'], $mime);
        if (!$src) {
            throw new RuntimeException('The image could not be processed.');
        }

        $maxW = (int) config('uploads.max_width', 1600);
        $maxH = (int) config('uploads.max_height', 1600);
        $dst  = self::resizeToFit($src, $width, $height, $maxW, $maxH);
        if ($dst !== $src) {
            imagedestroy($src);
        }

        $ext = self::ALLOWED[$mime] === 'gif' ? 'png' : self::ALLOWED[$mime];
        $dir = rtrim($subdir, '/') . '/' . date('Y/m');
        $absDir = UPLOAD_DIR . '/' . $dir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            imagedestroy($dst);
            throw new RuntimeException('Upload folder is not writable: uploads/' . $dir);
        }

        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $abs  = $absDir . '/' . $name;

        $ok = match ($ext) {
            'jpg'  => imagejpeg($dst, $abs, 86),
            'png'  => imagepng($dst, $abs, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $abs, 86) : imagejpeg($dst, $abs, 86),
            default => false,
        };
        imagedestroy($dst);

        if (!$ok) {
            throw new RuntimeException('Could not write the image to disk.');
        }
        @chmod($abs, 0644);

        return $dir . '/' . $name;
    }

    private static function loadImage(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
    }

    private static function resizeToFit($src, int $w, int $h, int $maxW, int $maxH)
    {
        if ($w <= $maxW && $h <= $maxH) {
            // Still re-encode: copy into a fresh canvas so nothing rides along.
            $out = imagecreatetruecolor($w, $h);
            self::preserveAlpha($out);
            imagecopy($out, $src, 0, 0, 0, 0, $w, $h);
            return $out;
        }
        $ratio = min($maxW / $w, $maxH / $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $out = imagecreatetruecolor($nw, $nh);
        self::preserveAlpha($out);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $out;
    }

    private static function preserveAlpha($img): void
    {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $transparent);
        imagealphablending($img, true);
    }

    /**
     * Delete a stored file. The path is checked against the uploads folder so a
     * crafted database row can never make us unlink something outside it.
     */
    public static function delete(?string $relative): void
    {
        if (!$relative) {
            return;
        }
        $abs = realpath(UPLOAD_DIR . '/' . ltrim($relative, '/'));
        $root = realpath(UPLOAD_DIR);
        if ($abs && $root && str_starts_with($abs, $root . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
    }
}
