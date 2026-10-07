<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Name, logo and colour for every email, from the same settings the panel uses.
 *
 * The logo has to be a raster image at an absolute URL: mail clients do not
 * render SVG and cannot reach a relative path. An uploaded logo wins; the app
 * icon is the fallback.
 */
class MailBrand
{
    public static function name(): string
    {
        $name = (string) Setting::get('brand_name', '');
        if ($name !== '') return $name;
        $app = (string) config('app.name');
        return $app !== '' && $app !== 'Laravel' ? $app : 'VortexOps';
    }

    public static function logoUrl(): ?string
    {
        $path = Setting::get('logo_path');
        if ($path && ! str_ends_with(strtolower((string) $path), '.svg') && file_exists(storage_path('app/public/'.$path))) {
            return asset('storage/'.$path);
        }

        return file_exists(public_path('icons/icon-192.png')) ? asset('icons/icon-192.png') : null;
    }

    public static function color(): string
    {
        $c = (string) Setting::get('primary_color', '');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#7c3aed';
    }

    public static function preferencesUrl(): string
    {
        try {
            return \App\Filament\Pages\MyNotifications::getUrl(panel: 'admin');
        } catch (\Throwable) {
            return url('/admin');
        }
    }
}
