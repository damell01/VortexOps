<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
            Archived pallets are hidden from normal receiving/history screens, but they are not permanently destroyed. Restore brings the pallet and its reversed receipt inventory back.
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
