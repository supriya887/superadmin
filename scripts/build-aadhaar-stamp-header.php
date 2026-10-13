<?php

$outPath = __DIR__ . '/../public/home/images/aadhaar-signature-valid-header.png';
$w = 186;
$h = 84;

if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension required.\n");
    exit(1);
}

$im = imagecreatetruecolor($w, $h);
imagealphablending($im, false);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 255, 255, 255, 127);
imagefill($im, 0, 0, $transparent);
imagealphablending($im, true);

$black = imagecolorallocate($im, 0, 0, 0);
$green = imagecolorallocate($im, 69, 224, 69);
$greenDark = imagecolorallocate($im, 47, 155, 47);
$shadow = imagecolorallocatealpha($im, 30, 30, 30, 60);

imagestring($im, 5, 44, 2, 'Signature valid', $black);

imagesetthickness($im, 11);
imageline($im, 47, 47, 71, 71, $shadow);
imageline($im, 71, 71, 135, 29, $shadow);
imageline($im, 45, 45, 69, 69, $green);
imageline($im, 69, 69, 133, 27, $green);
imagesetthickness($im, 3);
imageline($im, 45, 45, 69, 69, $greenDark);
imageline($im, 69, 69, 133, 27, $greenDark);

imagepng($im, $outPath);
imagedestroy($im);

echo "Wrote {$outPath}\n";
