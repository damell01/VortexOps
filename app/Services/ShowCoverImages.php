<?php

namespace App\Services;

use App\Jobs\StoreShowCover;
use App\Models\Show;

class ShowCoverImages
{
    public function capture(Show $show, array $raw): void
    {
        $source = (new WhatnotDataNormalizer)->normalizeCoverImageUrl($raw['cover_image_url'] ?? null);
        if (! $source || str_contains($source, '/storage/show-covers/')) return;
        $path = 'show-covers/'.$show->id.'-'.substr(hash('sha256', $source), 0, 24).'.webp';
        if ($show->cover_image_url === '/storage/'.$path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) return;
        $payload = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        if (($payload['cover_image_url'] ?? null) !== $source) {
            $payload['cover_image_url'] = $source;
            $show->forceFill(['raw_import_payload' => $payload])->saveQuietly();
        }
        StoreShowCover::dispatch($show->id, $source)->afterCommit();
    }

    /** Gradually migrate URLs captured before local storage was enabled. */
    public function backfillKnown(?int $channelId, int $limit = 25): void
    {
        Show::query()->when($channelId, fn ($q) => $q->where('whatnot_channel_id', $channelId))
            ->whereNotNull('cover_image_url')->where('cover_image_url', 'like', 'http%')
            ->where('cover_image_url', 'not like', '%/storage/show-covers/%')
            ->orderByDesc('show_date')->limit($limit)->get()
            ->each(fn ($show) => $this->capture($show, ['cover_image_url' => $show->cover_image_url]));
    }

    /** One frame, no upscaling, maximum 640px and 120 KiB per stored cover. */
    public function encode(string $bytes): string
    {
        if (! function_exists('imagewebp')) throw new \RuntimeException('Show covers require PHP GD with WebP support.');
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 16000000) {
            throw new \RuntimeException('Invalid or oversized show cover.');
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) throw new \RuntimeException('Unsupported show cover format.');
        try {
            foreach ([640, 480, 320, 240] as $max) {
                $ratio = min(1, $max / max($size[0], $size[1]));
                $width = max(1, (int) round($size[0] * $ratio));
                $height = max(1, (int) round($size[1] * $ratio));
                $scaled = imagecreatetruecolor($width, $height);
                imagealphablending($scaled, false);
                imagesavealpha($scaled, true);
                imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
                try {
                    foreach ([72, 60, 48] as $quality) {
                        ob_start();
                        try { $ok = imagewebp($scaled, null, $quality); $encoded = ob_get_contents(); }
                        finally { ob_end_clean(); }
                        if ($ok && is_string($encoded) && strlen($encoded) > 0 && strlen($encoded) <= 120 * 1024) return $encoded;
                    }
                } finally { imagedestroy($scaled); }
            }
            throw new \RuntimeException('Show cover could not fit the storage limit.');
        } finally { imagedestroy($image); }
    }
}
