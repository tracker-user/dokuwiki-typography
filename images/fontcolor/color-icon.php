<?php
/**
 * Color icon generator
 *
 * Standalone endpoint: hit directly as an <img> URL by the font-color toolbar
 * picker. Renders a 16x16 swatch of the requested colour, as PNG (via GD) or
 * SVG (fallback when GD is unavailable).
 *
 * Local fork modifications vs upstream (2020-07-31):
 *   - The `color` request parameter is now strictly validated to be exactly
 *     six hexadecimal digits before use. Upstream fed $_GET['color'] straight
 *     into str_split()/hexdec(); on PHP 8 a non-string value (e.g. ?color[]=x)
 *     raises a TypeError, and unvalidated input reached hexdec(). Validating
 *     up front is both PHP-8-safe and more robust than blind (string) casts.
 *   - Host check made null-safe (HTTP_HOST may be unset; parse_url() host may
 *     be null), avoiding PHP 8.1 "passing null" deprecation warnings.
 *   - list() -> [] destructuring.
 */

// This endpoint emits binary image data; suppress any stray warning output
// that would corrupt the response.
error_reporting(0);

// Restrict to same-host referers (best-effort; HTTP_REFERER is client-supplied).
if (isset($_SERVER['HTTP_REFERER'])) {
    $host       = $_SERVER['HTTP_HOST'] ?? '';
    $refHost    = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) ?? '';
    $isSameHost = strcasecmp($host, $refHost) === 0;
} else {
    // assume same host if HTTP_REFERER is not available
    $isSameHost = true;
}

// Accept the colour only if it is exactly six hex digits.
$color      = $_GET['color'] ?? '';
$isColorSet = is_string($color) && preg_match('/^[0-9a-fA-F]{6}$/', $color) === 1;

if ($isSameHost && $isColorSet) {

    if (function_exists('imagecreatetruecolor')) {
        // render PNG image using PHP GD library
        [$red, $green, $blue] = str_split($color, 2);
        $img = imagecreatetruecolor(16, 16);
        imagefill($img, 0, 0, imagecolorallocate($img, hexdec($red), hexdec($green), hexdec($blue)));
        header('Content-type: image/png');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT');
        imagepng($img);
        imagedestroy($img);
    } else {
        // render SVG image
        $hex = '#'.strtolower($color);
        header('Content-type: image/svg+xml');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60*60*24) . ' GMT');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16">';
        echo '<rect width="100%" height="100%" fill="'.$hex.'"/>';
        echo '</svg>';
    }
}
