<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Streamer;

class StreamerProfitShare extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Profit Share';
    protected static ?int $navigationSort = 50;

    public function getTitle(): string
    {
        return 'Profit Share Packets';
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
        return 'filament.pages.streamer-profit-share';
    }

    public function getStreamer(): Streamer
    {
        return auth()->user()?->streamer ?? abort(403);
    }
}
