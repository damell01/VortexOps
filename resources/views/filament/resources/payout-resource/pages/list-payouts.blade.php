<x-filament-panels::page>
    @php($stats = $this->payoutStats())
    <div class="space-y-6">
        <p class="text-sm font-semibold text-[var(--vx-muted)]">This week: {{ $stats['label'] }}</p>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="vx-card p-4"><p class="text-xs font-semibold text-[var(--vx-muted)]">To pay this week</p><p class="vx-kpi-value mt-2">${{ number_format($stats['total'], 2) }}</p></div>
            <div class="vx-card p-4"><p class="text-xs font-semibold text-[var(--vx-muted)]">Streamers</p><p class="vx-kpi-value mt-2">{{ number_format($stats['streamers']) }}</p></div>
            <div class="vx-card p-4"><p class="text-xs font-semibold text-[var(--vx-muted)]">Loan deductions</p><p class="vx-kpi-value mt-2">−${{ number_format($stats['deductions'], 2) }}</p><p class="mt-1 text-xs text-[var(--vx-faint)]">Auto-deducted</p></div>
            <div class="vx-card p-4"><p class="text-xs font-semibold text-[var(--vx-muted)]">On hold</p><p class="vx-kpi-value mt-2">{{ number_format($stats['hold']) }}</p><p class="mt-1 text-xs text-[var(--vx-faint)]">Needs review</p></div>
        </div>

        <div class="vx-card overflow-hidden">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
