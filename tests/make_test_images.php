<?php
/** Create distinct test photos: big red PNG, blue WebP, green JPG + oversize JPEG. */
$dir = 'C:/Users/Haseeb/AppData/Local/Temp/opencode';
@mkdir($dir, 0775, true);

function make(array $color, int $size = 320): \GdImage {
    $im = imagecreatetruecolor($size, $size);
    $c = imagecolorallocate($im, ...$color);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $c);
    imagestring($im, 5, 40, $size / 2 - 10, 'TEST', imagecolorallocate($im, 255, 255, 255));
    return $im;
}
$red = make([200, 30, 30]);   imagepng($red, $dir . '/test_red.png');    imagedestroy($red);
$blue = make([30, 80, 220]);  imagewebp($blue, $dir . '/test_blue.webp'); imagedestroy($blue);
$green = make([20, 160, 60]); imagejpeg($green, $dir . '/test_green.jpg', 90); imagedestroy($green);

// oversize: noise-filled valid jpeg > 2 MB
$big = imagecreatetruecolor(1600, 1600);
for ($i = 0; $i < 1600 * 1600; $i++) {
    imagesetpixel($big, $i % 1600, intdiv($i, 1600), imagecolorallocate($big, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
}
imagejpeg($big, $dir . '/test_oversize.jpg', 100);
echo 'oversize size: ' . round(filesize($dir . '/test_oversize.jpg') / 1024 / 1024, 1) . " MB\n";
foreach (['test_red.png', 'test_blue.webp', 'test_green.jpg', 'test_oversize.jpg'] as $f) {
    $info = getimagesize($dir . '/' . $f);
    echo $f, ' => ', $info ? $info['mime'] : 'INVALID', ' ', (int)$info[0] . 'x' . (int)$info[1], "\n";
}
