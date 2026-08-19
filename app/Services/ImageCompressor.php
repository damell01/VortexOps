<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Shrink an uploaded photo to something worth storing.
 *
 * A phone camera writes 3–8MB at 4000px on its long edge. Nothing here shows
 * a product larger than a few hundred pixels, so the other 95% of that file is
 * paid for on every upload, every backup, and every page that renders the
 * image — for pixels no one sees.
 *
 * Done after the file has landed rather than in the browser: Filament's
 * client-side resize hangs off ->image(), which makes Laravel re-stat the
 * Livewire temp file at save time, and that is what made saving an item with a
 * photo fail outright. This runs on a file that is already safely written, so
 * a failure here costs quality and never the upload.
 *
 * GD only, which ships with the container. PDFs and anything else are left
 * exactly as they are — there is nothing useful to do to them here, and
 * quietly rewriting a document is worse than storing a large one.
 */
class ImageCompressor
{
    /** Longest edge to keep. Comfortably above any thumbnail or detail view. */
    public const MAX_EDGE = 1600;

    /** Re-encode quality for the lossy formats. */
    public const QUALITY = 78;

    /** Formats worth touching. GIF is excluded: re-encoding drops animation. */
    private const HANDLERS = [
        'image/jpeg' => 'jpeg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Compress a stored image in place.
     *
     * @return bool whether the stored file was actually replaced
     */
    public function compress(string $disk, string $path): bool
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return false;
        }

        $mime = $storage->mimeType($path) ?: '';

        if (! isset(self::HANDLERS[$mime]) || ! \extension_loaded('gd')) {
            return false;
        }

        $original = $storage->get($path);

        try {
            $compressed = $this->reencode($original, self::HANDLERS[$mime]);
        } catch (\Throwable) {
            // A file GD cannot read is left alone rather than lost.
            return false;
        }

        // Only when it actually helps. Re-encoding a small or already-optimised
        // image can make it bigger, and storing that would be worse than doing
        // nothing at all.
        if ($compressed === null || strlen($compressed) >= strlen($original)) {
            return false;
        }

        $storage->put($path, $compressed);

        return true;
    }

    /** @return string|null the new bytes, or null if nothing could be done */
    private function reencode(string $bytes, string $format): ?string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        try {
            $image = $this->scaleToFit($image);

            ob_start();

            match ($format) {
                'jpeg' => imagejpeg($image, null, self::QUALITY),
                // PNG quality is a 0–9 compression level, not a percentage.
                'png'  => imagepng($image, null, 8),
                'webp' => imagewebp($image, null, self::QUALITY),
            };

            return ob_get_clean() ?: null;
        } finally {
            imagedestroy($image);
        }
    }

    /** @param \GdImage $image */
    private function scaleToFit(\GdImage $image): \GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $edge   = max($width, $height);

        if ($edge <= self::MAX_EDGE) {
            return $image;
        }

        $scaled = imagescale($image, (int) round($width * self::MAX_EDGE / $edge));

        if ($scaled === false) {
            return $image;
        }

        imagedestroy($image);

        return $scaled;
    }
}
