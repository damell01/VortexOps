<x-filament-panels::page>
    <div class="space-y-6">
        @livewire('manager-profit-share-dashboard', [
            'manager' => $this->getManager(),
        ])
    </div>
</x-filament-panels::page>
