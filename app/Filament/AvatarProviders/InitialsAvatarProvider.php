<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatars drawn in place, as an inline SVG.
 *
 * Filament's default asks ui-avatars.com for the picture, which is an outside
 * request (about a quarter of a second) on every page for anyone without a
 * photo. This draws the same thing with nothing to fetch.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#1e1b4b"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Inter,system-ui,sans-serif" font-size="26" font-weight="600" fill="#ffffff">'
            .e($initials !== '' ? $initials : '?')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
