<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Streamer;

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
        return auth()->user()?->isStreamer() ?? false;
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
