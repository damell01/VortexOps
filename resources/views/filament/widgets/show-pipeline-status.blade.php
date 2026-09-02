@php
    $toneClasses = match ($state['tone']) {
        'success' => 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30',
        'warning' => 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
        'danger' => 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30',
        'purple' => 'border-purple-200 bg-purple-50 dark:border-purple-900 dark:bg-purple-950/30',
        'primary', 'info' => 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30',
        default => 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50',
    };
    $currentStep = (int) ($state['step'] ?? 0);
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3 sm:space-y-4">
        <section class="rounded-xl border p-4 sm:rounded-2xl sm:p-5 {{ $toneClasses }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Current Workflow Stage</div>
                    <h3 class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">{{ $state['label'] }}</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-600 dark:text-gray-300 sm:text-sm">{{ $state['description'] }}</p>
                    @if(!empty($state['blockers']))
                        <div class="mt-2 space-y-1">
                            @foreach($state['blockers'] as $blocker)
                                <div class="text-xs font-medium text-amber-800 dark:text-amber-200">• {{ $blocker }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if($payRun)
                    <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Open Pay Run</a>
                @elseif(in_array($state['key'], ['streamer_log','admin_review'], true))
                    <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Open Show Report</a>
                @elseif(in_array($state['key'], ['fulfillment','payroll_ready'], true))
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Open Fulfillment</a>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Show → Payroll Flow</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">One status line across streamer logging, admin approval, fulfillment and payroll.</p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4 xl:grid-cols-7">
                @foreach($steps as $index => $step)
                    @php $stepNumber = $index + 1; $done = $currentStep > $stepNumber; $active = $currentStep === $stepNumber; @endphp
                    <div class="bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                        <div class="flex items-center gap-2">
                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $done ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : ($active ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-400 dark:bg-gray-800') }}">{{ $done ? '✓' : $stepNumber }}</div>
                            <div class="truncate text-[10px] font-semibold {{ $active ? 'text-primary-700 dark:text-primary-300' : 'text-gray-600 dark:text-gray-300' }} sm:text-xs">{{ $step['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Financial & Payroll Snapshot</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">The same show calculations the weekly payroll dashboard rolls up.</p>
                </div>
                @if($payRun)<span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">Pay Run #{{ $payRun->id }}</span>@endif
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                @foreach([
                    ['Gross Sales', $pnl['gross'] ?? 0],
                    ['Whatnot Net', $pnl['net'] ?? 0],
                    ['Tips', $pnl['tips'] ?? 0],
                    ['COGS', $pnl['cogs'] ?? 0],
                    ['Payroll', $pnl['payouts'] ?? 0],
                    ['Show Net', $pnl['margin'] ?? 0],
                ] as [$label,$value])
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-[10px] font-medium text-gray-500 sm:text-xs">{{ $label }}</div>
                        <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">${{ number_format((float)$value,2) }}</div>
                    </div>
                @endforeach
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                    <div class="text-[10px] font-medium text-gray-500 sm:text-xs">Margin</div>
                    <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">{{ number_format((float)($pnl['margin_pct'] ?? 0),1) }}%</div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div><div class="text-[10px] uppercase tracking-wide text-gray-500">Report Units</div><div class="mt-1 text-lg font-semibold">{{ number_format($reportUnits) }}</div></div>
                <div><div class="text-[10px] uppercase tracking-wide text-gray-500">Inventory Issues</div><div class="mt-1 text-lg font-semibold">{{ number_format($inventoryIssues) }}</div></div>
                <div><div class="text-[10px] uppercase tracking-wide text-gray-500">Open Shipments</div><div class="mt-1 text-lg font-semibold">{{ number_format($openShipmentCount) }}</div></div>
                <div><div class="text-[10px] uppercase tracking-wide text-gray-500">Payroll Entries</div><div class="mt-1 text-lg font-semibold">{{ number_format($payouts->count()) }}</div></div>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
