<?php

namespace App\Support;

use App\Models\Setting;

class ReportBranding
{
    public static function logoData(): ?string
    {
        $paths = [];

        $channel = ChannelContext::current();
        if ($channel?->logo_path) {
            $paths[] = storage_path('app/public/' . ltrim($channel->logo_path, '/'));
        }

        $configured = Setting::get('logo_path');
        if ($configured) {
            $paths[] = storage_path('app/public/' . ltrim($configured, '/'));
        }

        $paths[] = public_path('images/vb-logo.png');
        $paths[] = public_path('images/vb-logo.jpg');
        $paths[] = public_path('images/vb-logo.svg');

        foreach ($paths as $path) {
            if (! is_file($path) || ! is_readable($path)) continue;

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => null,
            };

            if (! $mime) continue;

            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
        }

        return null;
    }

    public static function name(): string
    {
        return config('app.name', 'VortexOps');
    }

    public static function generatedLabel(?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        return 'Generated ' . $date->format('M j, Y g:i A');
    }
}
