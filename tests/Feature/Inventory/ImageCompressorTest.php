<?php

namespace Tests\Feature\Inventory;

use App\Services\ImageCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Shrinking what a phone camera hands over.
 *
 * A photo taken at the pallet is several megabytes at 4000px, and nothing in
 * the app renders a product above a few hundred — so the rest is paid for on
 * every backup and every page load, for pixels nobody sees.
 */
class ImageCompressorTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'public';

    /** A real JPEG of the given size, noisy enough not to compress to nothing. */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, imagecolorallocate(
                    $image, ($x * 7) % 255, ($y * 13) % 255, ($x + $y) % 255,
                ));
            }
        }

        ob_start();
        imagejpeg($image, null, 100);
        imagedestroy($image);

        return ob_get_clean();
    }

    private function dimensions(string $bytes): array
    {
        $image = imagecreatefromstring($bytes);
        $size  = [imagesx($image), imagesy($image)];
        imagedestroy($image);

        return $size;
    }

    public function test_an_oversized_photo_is_scaled_down_and_shrinks(): void
    {
        Storage::fake(self::DISK);
        Storage::disk(self::DISK)->put('products/big.jpg', $this->jpeg(3000, 2000));

        $before = Storage::disk(self::DISK)->size('products/big.jpg');

        $this->assertTrue(app(ImageCompressor::class)->compress(self::DISK, 'products/big.jpg'));

        $after = Storage::disk(self::DISK)->get('products/big.jpg');

        $this->assertLessThan($before, strlen($after));

        [$width, $height] = $this->dimensions($after);

        $this->assertSame(ImageCompressor::MAX_EDGE, $width, 'The long edge should be capped.');
        // Within a pixel: GD floors where the arithmetic rounds, and a test
        // that pins the rounding rule tests GD rather than this.
        $this->assertEqualsWithDelta(2000 * ImageCompressor::MAX_EDGE / 3000, $height, 1.0);
    }

    public function test_a_small_photo_keeps_its_dimensions(): void
    {
        // Only the long edge is capped — a small image is re-encoded at most,
        // never stretched up to the maximum.
        Storage::fake(self::DISK);
        Storage::disk(self::DISK)->put('products/small.jpg', $this->jpeg(400, 300));

        app(ImageCompressor::class)->compress(self::DISK, 'products/small.jpg');

        $this->assertSame([400, 300], $this->dimensions(Storage::disk(self::DISK)->get('products/small.jpg')));
    }

    public function test_a_file_that_would_grow_is_left_alone(): void
    {
        // Re-encoding an image already saved at a lower quality than ours makes
        // it bigger, and storing that would be worse than doing nothing.
        Storage::fake(self::DISK);

        $image = imagecreatefromstring($this->jpeg(600, 400));
        ob_start();
        imagejpeg($image, null, 25);
        imagedestroy($image);
        $alreadySmall = ob_get_clean();

        Storage::disk(self::DISK)->put('products/lean.jpg', $alreadySmall);

        $this->assertFalse(app(ImageCompressor::class)->compress(self::DISK, 'products/lean.jpg'));
        $this->assertSame($alreadySmall, Storage::disk(self::DISK)->get('products/lean.jpg'));
    }

    public function test_a_document_is_never_rewritten(): void
    {
        // Quietly re-encoding a PDF is worse than storing a large one.
        Storage::fake(self::DISK);
        Storage::disk(self::DISK)->put('pallets/slip.pdf', '%PDF-1.4 not really a pdf');

        $this->assertFalse(app(ImageCompressor::class)->compress(self::DISK, 'pallets/slip.pdf'));
        $this->assertSame('%PDF-1.4 not really a pdf', Storage::disk(self::DISK)->get('pallets/slip.pdf'));
    }

    public function test_an_unreadable_image_is_left_alone_rather_than_lost(): void
    {
        Storage::fake(self::DISK);
        Storage::disk(self::DISK)->put('products/broken.jpg', 'this is not an image');

        $this->assertFalse(app(ImageCompressor::class)->compress(self::DISK, 'products/broken.jpg'));
        $this->assertSame('this is not an image', Storage::disk(self::DISK)->get('products/broken.jpg'));
    }

    public function test_a_missing_file_is_not_an_error(): void
    {
        Storage::fake(self::DISK);

        $this->assertFalse(app(ImageCompressor::class)->compress(self::DISK, 'products/nothing.jpg'));
    }

    public function test_pallet_attachments_are_compressed_on_the_way_in(): void
    {
        Storage::fake(self::DISK);

        $pallet = \App\Models\Pallet::create([
            'vendor_id' => \App\Models\Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'status'    => 'receiving',
        ]);

        Storage::disk(self::DISK)->put('pallets/damage.jpg', $this->jpeg(3000, 2000));
        $before = Storage::disk(self::DISK)->size('pallets/damage.jpg');

        app(\App\Services\PalletAttachmentService::class)->attach($pallet, ['pallets/damage.jpg'], 'damage');

        $this->assertSame(1, $pallet->attachments()->count());
        $this->assertLessThan($before, Storage::disk(self::DISK)->size('pallets/damage.jpg'));

        // The recorded size has to be the size actually on disk, not the one
        // before compression, or every listing reports a file that is not there.
        $this->assertSame(
            Storage::disk(self::DISK)->size('pallets/damage.jpg'),
            (int) $pallet->attachments()->first()->file_size,
        );
    }
}
