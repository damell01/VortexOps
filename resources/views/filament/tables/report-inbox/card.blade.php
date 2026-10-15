@php
    use App\Filament\Resources\StreamerLogResource;
    $record = $getRecord();
    $show = $record->show;
    $user = auth()->user();
    $staff = $user?->isAdmin() || $user?->isOwner() || $user?->isFulfillmentAdmin();
    $mine = $user?->streamer?->id !== null && $user->streamer->id === $record->streamer_id;
    $title = $show?->title ?: 'Report #' . $record->id;
    $name = $record->streamer?->name ?: 'No streamer';
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

    [$label, $tone] = match (true) {
        $record->hasPendingRevisionRequest() => ['Edit requested', 'rose'],
        $record->status === 'streamer_reviewed' => ['Ready for review', 'violet'],
        $record->status === 'changes_requested' => ['Changes requested', 'amber'],
        $record->status === 'admin_approved' => ['Approved', 'green'],
        default => ['In progress', 'gray'],
    };

    // One line on why it is where it is, when there is something to say.
    $why = match (true) {
        $record->hasPendingRevisionRequest() => ['rose', 'heroicon-m-lock-open', 'Asked to reopen: ' . ($record->revision_reason ?: 'no reason given')],
        $record->status === 'changes_requested' && filled($record->approval_notes) => ['amber', 'heroicon-m-exclamation-triangle', \Illuminate\Support\Str::of($record->approval_notes)->explode("\n")->filter()->last()],
        $record->status === 'admin_approved' && $record->needsFulfillmentReview() => ['blue', 'heroicon-m-truck', 'Needs fulfillment review'],
        default => null,
    };

    $missing = $staff ? StreamerLogResource::missingInfo($record) : [];
    $pay = ($staff || $mine) ? StreamerLogResource::payFor($record) : null;
    $payLabel = match ($pay['state'] ?? null) { 'paid' => 'Paid', 'final' => 'On pay run', 'draft' => 'Pay (draft)', default => 'Est. pay' };
    $money = fn ($v) => $v === null ? '—' : ((float) $v < 0 ? '−$' : '$') . number_format(abs((float) $v), 2);
    $zone = \App\Support\BusinessTime::zone();
@endphp
<article class="vxi-card">
    <header class="vxi-card-top">
        @if ($show?->channel?->name)
            <span class="vxi-chan"><span class="vxi-chan-dot" aria-hidden="true"></span>{{ $show->channel->name }}</span>
        @else
            <span class="vxi-chan vxi-chan--none">No channel</span>
        @endif
        <span class="vxi-pill vxi-{{ $tone }}">{{ $label }}</span>
    </header>

    <div>
        <h3 class="vxi-card-title" title="{{ $title }}">{{ $title }}</h3>
        <div class="vxi-meta">{{ $show?->show_date?->format('M j, Y') ?? 'No date' }}</div>
    </div>

    <div class="vxi-who"><span class="vxi-initials" aria-hidden="true">{{ $initials ?: '?' }}</span>{{ $name }}</div>

    @if ($why)
        <div class="vxi-strip vxi-{{ $why[0] }}" title="{{ $why[2] }}"><x-filament::icon :icon="$why[1]" class="h-4 w-4 shrink-0" /><span class="truncate">{{ $why[2] }}</span></div>
    @endif
    @if ($missing)
        <div class="vxi-strip vxi-amber vxi-missing" title="{{ implode(' · ', $missing) }}">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4 shrink-0" />
            <span><strong>Missing:</strong> {{ implode(' · ', $missing) }}</span>
        </div>
    @endif

    <dl class="vxi-figs">
        <div><dt>Sales</dt><dd>{{ $money($record->gross_revenue ?? $show?->gross_revenue) }}</dd></div>
        <div><dt>Product cost</dt><dd>{{ $money($record->product_cost) }}</dd></div>
        @if ($staff || $mine)
            <div class="vxi-pay {{ ($pay['amount'] ?? 0) < 0 ? 'is-negative' : '' }}" @if ($staff && filled($pay['notes'] ?? null)) title="{{ $pay['notes'] }}" @endif>
                <dt>{{ $payLabel }}</dt><dd>{{ $pay ? $money($pay['amount']) : '—' }}</dd>
            </div>
        @endif
    </dl>

    <footer class="vxi-card-foot">
        {{ $record->submitted_at ? 'Submitted ' . $record->submitted_at->timezone($zone)->format($record->submitted_at->isCurrentYear() ? 'M j, g:i A' : 'M j, Y') : 'Not submitted yet' }}
    </footer>
</article>
