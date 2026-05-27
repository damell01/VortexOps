<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReviewScreenshotStore
{
    public static function persist(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (! str_starts_with($value, 'data:image/')) {
            return $value;
        }

        if (! preg_match('/^data:image\/(?P<type>[a-zA-Z0-9.+-]+);base64,(?P<data>.+)$/', $value, $matches)) {
            return $value;
        }

        $binary = base64_decode($matches['data'], true);

        if ($binary === false) {
            return $value;
        }

        $extension = match (strtolower($matches['type'])) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $path = 'review-screenshots/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $extension;

        try {
            Storage::disk('public')->makeDirectory(dirname($path));
            Storage::disk('public')->put($path, $binary);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $path;
    }
}
