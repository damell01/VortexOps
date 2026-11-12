<x-filament-panels::page>
    <div wire:poll.30s class="space-y-5">
        <div class="text-sm text-gray-500 dark:text-gray-400">Live order freshness and historical catch-up are tracked separately. Refreshes every 30 seconds.</div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->getChannelHealth() as $item)
                @php($fresh = $item['fresh'])
                @php($catchup = $item['catchup'])
                <x-filament::section>
                    <x-slot name="heading">Channel {{ $item['channel'] }}</x-slot>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span>Live sync</span>
                            <x-filament::badge :color="$item['healthy'] ? 'success' : 'danger'">
                                {{ $item['healthy'] ? 'Healthy' : 'Needs attention' }}
                            </x-filament::badge>
                        </div>
                        <div>Last successful: <strong>{{ $fresh?->last_success_at ?? 'Never' }}</strong></div>
                        <div>Age: {{ $item['age'] === null ? '—' : $item['age'].' min' }}</div>
                        <div>Runtime: {{ $fresh?->duration_seconds ?? '—' }}s</div>
                        <div>Live pages: {{ $fresh?->orders_pages ?? '—' }}</div>
                        <div>Live page cap reached: {{ $fresh?->orders_incomplete ? 'Yes' : 'No' }}</div>
                        <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
                            <strong>Historical reconciliation</strong>
                            <div>Last run: {{ $catchup?->last_run_at ?? 'Never' }}</div>
                            <div>Last completed: {{ $catchup?->last_complete_at ?? 'Not yet' }}</div>
                            <div>Catch-up pages: {{ $catchup?->orders_pages ?? '—' }}</div>
                            <div>More pages pending: {{ $catchup?->orders_incomplete ? 'Yes' : 'No' }}</div>
                        </div>
                        @foreach ($item['errors'] as $error)
                            <div class="text-danger-600 break-words">{{ $error }}</div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
