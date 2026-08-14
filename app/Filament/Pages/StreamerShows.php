<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Streamer;
use App\Support\NavVisibility;

class StreamerShows extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'My Shows';
    protected static ?int $navigationSort = 10;

    public function getTitle(): string
    {
        return 'My Shows';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Streamer';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-shows';
    }

    public function getStreamer(): Streamer
    {
        return auth()->user()?->streamer ?? abort(403);
    }
}
