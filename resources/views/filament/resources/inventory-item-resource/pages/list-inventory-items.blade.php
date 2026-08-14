<x-filament-panels::page>
    {{-- Headline counts. These read the resource's own scoped query, so a
         streamer's tiles agree with the rows listed underneath. --}}
    <x-kpi-row :stats="$this->getStats()" />

    {{-- The table renders directly rather than behind a skeleton swap: the
         two wrappers were flex siblings, so the hidden one still spent a
         gap between the tiles and the table. Filament's own deferred
         loading state covers the wait. --}}
    {{ $this->table }}
</x-filament-panels::page>
