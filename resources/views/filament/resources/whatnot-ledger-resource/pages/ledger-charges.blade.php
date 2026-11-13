<x-filament-panels::page>
<x-reports.shell>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index') }}">‹ Ledger</a>
        <a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('tips') }}">Tip totals</a>
    </div>
    <livewire:ledger-charges />
</x-reports.shell>
</x-filament-panels::page>
