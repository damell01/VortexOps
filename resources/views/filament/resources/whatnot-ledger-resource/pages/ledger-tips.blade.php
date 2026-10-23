<x-filament-panels::page>
    @php $report = $this->report(); @endphp
    <div class="vxw" data-vx-page="ledger-tips">
        <section class="vxw-card">
            <div class="vxw-card-body grid gap-4">
                <div class="flex flex-wrap justify-between gap-3">
                    <p>Review imported Whatnot tips by channel and business before deciding how to pay them.</p>
                    <a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index') }}">Open ledger</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="grid gap-1">Period
                        <select class="vxw-select" wire:model.live="period">
                            @foreach ($this::PERIODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-1">Channel
                        <select class="vxw-select" wire:model.live="channel">
                            <option value="all">All channels</option>
                            @foreach ($this->channels() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            <option value="unassigned">Unassigned channel</option>
                        </select>
                    </label>
                    <label class="grid gap-1">Business
                        <select class="vxw-select" wire:model.live="business">
                            <option value="all">All businesses</option>
                            @foreach ($this->businesses() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            <option value="unassigned">Unassigned business</option>
                        </select>
                    </label>
                    @if ($period === 'custom')
                        <label class="grid gap-1">From<input class="vxw-input" type="date" wire:model.live="from"></label>
                        <label class="grid gap-1">Through<input class="vxw-input" type="date" wire:model.live="until"></label>
                    @elseif ($period === 'pay_period')
                        <label class="grid gap-1">Pay run dates
                            <select class="vxw-select" wire:model.live="payRun">
                                <option value="">Choose a pay run</option>
                                @foreach ($this->payRuns() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                    @endif
                </div>
                @foreach ($this->getErrorBag()->all() as $error)<p role="alert" class="text-sm text-red-600">{{ $error }}</p>@endforeach
            </div>
        </section>
        @if ($report)
            @php [$fromDate, $untilDate] = $this->range(); @endphp
            <p class="vxw-meta">{{ $fromDate->format('M j, Y') }} – {{ $untilDate->format('M j, Y') }} · Ledger created dates · Live imported totals</p>
            @include('filament.reports.ledger-tip-summary', ['tipReport' => $report])
            <section class="vxw-card">
                <div class="vxw-card-head"><h2 class="vxw-h2">Weekly breakdown</h2></div>
                <div class="vxw-card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($report['weeks'] as $row)
                        <div class="vxw-card p-4">
                            <strong>Week of {{ \Illuminate\Support\Carbon::parse($row['week'])->format('M j, Y') }}</strong>
                            <p>{{ $row['channel'] }} · {{ $row['business'] }}</p>
                            <p class="text-lg font-semibold">${{ number_format($row['net'] / 100, 2) }}</p>
                            <p class="vxw-meta">Credits ${{ number_format($row['credits'] / 100, 2) }} · Debits ${{ number_format($row['debits'] / 100, 2) }}</p>
                        </div>
                    @empty
                        <p class="vxw-meta">No imported tips for these filters.</p>
                    @endforelse
                </div>
                <div class="vxw-card-body vxw-meta">Weeks start {{ \App\Support\PaySchedule::startDayName() }}. First and last weeks include only dates inside the selected range.</div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
