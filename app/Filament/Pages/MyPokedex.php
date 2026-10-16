<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * A streamer's own Pokédex (App\Support\Pokedex): every approved show report
 * catches one. Its own page, linked from the dashboard, so the sprites only
 * load when someone opens it.
 */
class MyPokedex extends Page
{
    protected static ?string $title = 'My Pokédex';
    protected static ?string $slug = 'my-pokedex';
    protected static bool $shouldRegisterNavigation = false;

    public function getView(): string { return 'filament.pages.my-pokedex'; }
    public function getSubheading(): ?string { return 'Every approved show report catches a Pokémon. Gotta catch ’em all!'; }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->streamer()->exists();
    }
}
