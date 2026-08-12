<x-filament-panels::page>
    <div class="space-y-6">
        @livewire('streamer-profit-share-dashboard', [
            'streamer' => $this->getStreamer(),
        ])
    </div>
</x-filament-panels::page>
