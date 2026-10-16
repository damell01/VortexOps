<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/** The team member's Pokédex on their profile page (one Pokémon per approved show report). */
class StreamerPokedexWidget extends Widget
{
    protected string $view = 'filament.widgets.streamer-pokedex';

    /** Only on a team member's page, never picked up for the dashboard. */
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;
}
