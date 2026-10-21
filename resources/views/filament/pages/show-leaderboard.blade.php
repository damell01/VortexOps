<x-filament-panels::page>
    @vite('resources/js/colosseum.js')
    <livewire:streamer-leaderboard :arena="true" :key="'show-leaderboard-page'" />
</x-filament-panels::page>
