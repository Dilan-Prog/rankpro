<?php
/*
|--------------------------------------------------------------------------
| Generador de imagenes de marca
|--------------------------------------------------------------------------
|
| Reconstruye desde los PNG maestros de storage/app/logos-originales/:
|   - logos redimensionados (493x160) en PNG y WebP
|   - og-rankpro.jpg 1200x630 para Open Graph / Twitter Card
|   - apple-touch-icon.png y icon-192/512.png (solo el simbolo, fondo navy)
|
| Uso:  php scripts/generar-imagenes-marca.php
|
| Los maestros originales eran de 3803x1233 px (122 KB) y se servian a 36 px
| de alto: se conservan intactos en storage/ y NUNCA se sirven al navegador.
|
*/

$root = 'C:/laragon/www/rankpro';
$orig = $root . '/storage/app/logos-originales';
$out  = $root . '/public/images';

function loadPng(string $f) {
    $im = imagecreatefrompng($f);
    if (!$im) { throw new RuntimeException("no se pudo abrir $f"); }
    imagealphablending($im, false);
    imagesavealpha($im, true);
    return $im;
}

function resizeTo($im, int $h) {
    $w0 = imagesx($im); $h0 = imagesy($im);
    $w = (int) round($w0 * $h / $h0);
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $w, $h, $w0, $h0);
    return [$dst, $w, $h];
}

$report = [];
foreach (['black', 'white'] as $variant) {
    $im = loadPng("$orig/rankpro-logo-$variant.png");
    [$r, $w, $h] = resizeTo($im, 160);

    imagepng($r, "$out/rankpro-logo-$variant.png", 9);
    imagewebp($r, "$out/rankpro-logo-$variant.webp", 90);

    $report[] = sprintf('%-8s %dx%d  png %s KB  webp %s KB', $variant, $w, $h,
        round(filesize("$out/rankpro-logo-$variant.png") / 1024, 1),
        round(filesize("$out/rankpro-logo-$variant.webp") / 1024, 1));

    imagedestroy($r); imagedestroy($im);
}

$src = loadPng("$orig/rankpro-logo-white.png");

// ---------- og-rankpro.jpg 1200x630 ----------
$og = imagecreatetruecolor(1200, 630);
imagefill($og, 0, 0, imagecolorallocate($og, 0x1A, 0x23, 0x32));
// franja de marca inferior
imagefilledrectangle($og, 0, 618, 1200, 630, imagecolorallocate($og, 0x0F, 0x9D, 0x6E));
[$lg2, $lw2, $lh2] = resizeTo($src, 104);
imagealphablending($og, true);
imagecopy($og, $lg2, (int)((1200 - $lw2) / 2), 210, 0, 0, $lw2, $lh2);

$font = 'C:/Windows/Fonts/arialbd.ttf';
if (is_file($font)) {
    $white = imagecolorallocate($og, 0xFF, 0xFF, 0xFF);
    $txt = 'Agencia de Marketing Digital en México';
    $bb = imagettfbbox(30, 0, $font, $txt);
    imagettftext($og, 30, 0, (int)((1200 - ($bb[2] - $bb[0])) / 2), 400, $white, $font, $txt);
    $grey = imagecolorallocate($og, 0x9C, 0xA8, 0xB4);
    $txt2 = 'SEO  ·  Google Ads  ·  Desarrollo Web  ·  Core Web Vitals';
    $bb2 = imagettfbbox(20, 0, $font, $txt2);
    imagettftext($og, 20, 0, (int)((1200 - ($bb2[2] - $bb2[0])) / 2), 455, $grey, $font, $txt2);
} else {
    $report[] = 'AVISO: Arial Bold no encontrado, og sin texto';
}
imagejpeg($og, "$out/og-rankpro.jpg", 86);
$report[] = 'og-rankpro.jpg 1200x630  ' . round(filesize("$out/og-rankpro.jpg") / 1024, 1) . ' KB';

echo implode(PHP_EOL, $report), PHP_EOL;

$orig = 'C:/laragon/www/rankpro/storage/app/logos-originales/rankpro-logo-white.png';
$out  = 'C:/laragon/www/rankpro/public/images';

$im = imagecreatefrompng($orig);
$w = imagesx($im); $h = imagesy($im);

// El divisor vertical del logo esta en x=1128..1146: el simbolo es todo lo anterior.
$markW = 1128;

// Recorte vertical real del simbolo (ignora el padding transparente).
$top = null; $bottom = null;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $markW; $x++) {
        if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 64) {
            if ($top === null) $top = $y;
            $bottom = $y;
            break;
        }
    }
}
$left = null; $right = null;
for ($x = 0; $x < $markW; $x++) {
    for ($y = 0; $y < $h; $y++) {
        if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 64) {
            if ($left === null) $left = $x;
            $right = $x;
            break;
        }
    }
}
$mw = $right - $left + 1; $mh = $bottom - $top + 1;
echo "simbolo recortado: {$mw}x{$mh} (x $left..$right, y $top..$bottom)\n";

function iconoDe(int $size, int $pad, string $file, array $bg, $im, $left, $top, $mw, $mh) {
    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...$bg));
    $box = $size - 2 * $pad;
    $scale = min($box / $mw, $box / $mh);
    $dw = (int) round($mw * $scale); $dh = (int) round($mh * $scale);
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $im,
        (int)(($size - $dw) / 2), (int)(($size - $dh) / 2),
        $left, $top, $dw, $dh, $mw, $mh);
    imagepng($canvas, $file, 9);
    imagedestroy($canvas);
    echo basename($file) . " {$size}x{$size}  " . round(filesize($file) / 1024, 1) . " KB\n";
}

$brand = [0x1A, 0x23, 0x32];
iconoDe(180, 34, "$out/apple-touch-icon.png", $brand, $im, $left, $top, $mw, $mh);
iconoDe(192, 36, "$out/icon-192.png",        $brand, $im, $left, $top, $mw, $mh);
iconoDe(512, 96, "$out/icon-512.png",        $brand, $im, $left, $top, $mw, $mh);
