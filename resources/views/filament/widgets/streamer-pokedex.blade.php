<x-filament-widgets::widget>
    <x-vx.pokedex :streamer="$record" :title="($record?->name ?? 'Their') . '’s Pokédex'" />
</x-filament-widgets::widget>
