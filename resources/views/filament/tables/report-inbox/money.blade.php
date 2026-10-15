@php
    $record = $getRecord();
    $user = auth()->user();
    $staff = $user?->isAdmin() || $user?->isOwner();
    $mine = $user?->streamer?->id !== null && $user->streamer->id === $record->streamer_id;
    $gross = $record->gross_revenue ?? $record->show?->gross_revenue;
    $cost = $record->product_cost;
    $pay = ($staff || $mine) ? \App\Filament\Resources\StreamerLogResource::payFor($record) : null;
    $payLabel = match ($pay['state'] ?? null) {
        'paid' => 'Paid',
        'final' => 'On pay run',
        'draft' => 'Pay (draft)',
        default => 'Est. pay',
    };
    $money = fn ($v) => $v === null ? '—' : ((float) $v < 0 ? '−$' : '$') . number_format(abs((float) $v), 2);
@endphp
<dl class="vxi-money">
    <div><dt>Sales</dt><dd>{{ $money($gross) }}</dd></div>
    <div><dt>Product cost</dt><dd>{{ $money($cost) }}</dd></div>
    @if ($staff || $mine)
        <div class="vxi-pay {{ ($pay['amount'] ?? 0) < 0 ? 'is-negative' : '' }}" @if ($staff && filled($pay['notes'] ?? null)) title="{{ $pay['notes'] }}" @endif>
            <dt>{{ $payLabel }}</dt>
            <dd>{{ $pay ? $money($pay['amount']) : '—' }}</dd>
        </div>
    @endif
</dl>
