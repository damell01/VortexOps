<?php
// Runs without Composer or a database so image handling can be verified independently.
require __DIR__.'/../app/Services/ShowCoverImages.php';

function check(bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
}
function png(int $width, int $height): string {
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 80, 140));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    return $bytes;
}
$encoder = new App\Services\ShowCoverImages;
foreach ([[1600, 900, 640, 360], [200, 300, 200, 300], [800, 1600, 320, 640]] as [$w, $h, $expectedW, $expectedH]) {
    $webp = $encoder->encode(png($w, $h));
    $size = getimagesizefromstring($webp);
    check($size['mime'] === 'image/webp', 'Output must be WebP');
    check($size[0] === $expectedW && $size[1] === $expectedH, 'Aspect ratio / no upscale check failed');
    check(strlen($webp) <= 120 * 1024, 'Storage cap exceeded');
}
try {
    $encoder->encode('<html>not an image</html>');
    throw new RuntimeException('Invalid input was accepted');
} catch (RuntimeException $e) {
    check($e->getMessage() === 'Invalid or oversized show cover.', 'Wrong invalid image behavior');
}
echo "Cover conversion, aspect ratio, no upscaling, byte cap and invalid input checks passed.\n";
