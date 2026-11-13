<x-filament-panels::page>
<x-reports.shell>
@php
    $signed = fn ($v) => ((float) $v < 0 ? '−' : '+') . '$' . number_format(abs((float) $v), 2);
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $tz = \App\Support\BusinessTime::zone();
    $owed = $rows->where('difference', '>', 0)->sum('difference');
    $over = $rows->where('difference', '<', 0)->sum('difference');
    $labels = ['gross_revenue' => 'Gross', 'whatnot_net' => 'Whatnot net', 'whatnot_fees' => 'Fees', 'tips' => 'Tips', 'units_sold' => 'Units sold'];
@endphp
<div class="vxw" data-vx-page="pay-changed">
    <section class="vxw-card">
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Lines changed</div><div class="vxw-stat-value {{ $rows->isNotEmpty() ? 'vxw-tone-warn' : 'vxw-tone-ok' }}">{{ $rows->count() }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Still owed to people</div><div class="vxw-stat-value">{{ $money($owed) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Overpaid</div><div class="vxw-stat-value">{{ $money(abs($over)) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Looking back</div><div class="vxw-stat-value">{{ \App\Services\PayChangedAfterPayout::DAYS }} days</div></div>
        </div>
    </section>

    <section class="vxw-card" style="margin-top:16px">
        <div class="vxw-card-head">
            <h3 class="vxw-h2">Paid, then changed</h3>
            <button type="button" class="vxw-btn vxw-btn--sm" wire:click="refreshList" wire:loading.attr="disabled"><x-filament::icon icon="heroicon-m-arrow-path" /> Check again</button>
        </div>
        @if($rows->isEmpty())
            <p class="vxw-meta" style="padding:16px 18px;margin:0">Nothing changed after it was paid. Every locked show still works out to what was paid.</p>
        @else
            <div style="overflow-x:auto">
                <table class="vxw-table" style="width:100%">
                    <thead><tr>
                        <th scope="col" style="text-align:left">Show</th><th scope="col" style="text-align:left">Person</th><th scope="col" style="text-align:left">Run</th>
                        <th scope="col" style="text-align:right">Paid</th><th scope="col" style="text-align:right">Now</th><th scope="col" style="text-align:right">Difference</th><th scope="col" style="text-align:left">What changed</th><th scope="col" style="text-align:right">Settle</th>
                    </tr></thead>
                    <tbody>
                        @foreach($rows as $r)
                            <tr wire:key="c-{{ $r['show_id'] }}-{{ $r['streamer_id'] }}">
                                <td><a class="vxw-link" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $r['show_id']]) }}" style="font-weight:600">{{ $r['show'] }}</a>
                                    <div class="vxw-meta">{{ $r['show_date'] ? \Illuminate\Support\Carbon::parse($r['show_date'])->format('M j, Y') : '' }}</div></td>
                                <td>{{ $r['streamer'] }}</td>
                                <td><a class="vxw-link" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $r['run_id']]) }}">#{{ $r['run_id'] }}</a>
                                    <div class="vxw-meta">{{ \App\Models\WeeklyPayoutBatch::statusLabels()[$r['run_status']] ?? $r['run_status'] }}{{ $r['locked_at'] ? ' · ' . \Illuminate\Support\Carbon::parse($r['locked_at'])->timezone($tz)->format('M j') : '' }}</div></td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($r['paid']) }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($r['now']) }}</td>
                                <td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums;{{ $r['difference'] > 0 ? 'color:#15803d' : 'color:#b91c1c' }}">{{ $signed($r['difference']) }}</td>
                                <td class="vxw-meta">
                                    @forelse($r['changes'] as $c)
                                        <div>{{ $labels[$c['field']] ?? $c['field'] }}: {{ $c['old'] ?? '—' }} → {{ $c['new'] ?? '—' }} <span>({{ \Illuminate\Support\Carbon::parse($c['at'])->timezone($tz)->format('M j') }})</span></div>
                                    @empty
                                        {{ $r['lines'] ? ucfirst(implode(', ', $r['lines'])) . ' line' . (count($r['lines']) > 1 ? 's' : '') : 'Show or report updated' }}
                                    @endforelse
                                    @if(($r['settled'] ?? 0) != 0)<div style="margin-top:4px">Already carried over: {{ $signed($r['settled']) }}</div>@endif
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    @php $target = $targets[$r['streamer_id']] ?? null; @endphp
                                    @if($target)
                                        <div x-data="{ open: false, note: '' }" style="position:relative;display:inline-block">
                                            <button type="button" class="vxw-btn vxw-btn--sm vxw-btn--primary" x-on:click="open = !open">Carry to next run</button>
                                            <div x-show="open" x-cloak x-on:click.outside="open = false" x-on:keydown.escape.window="if ($event.isTrusted) open = false"
                                                 style="position:absolute;right:0;top:calc(100% + 6px);z-index:30;width:300px;text-align:left;padding:14px;border-radius:14px;background:var(--vxw-surface,#fff);border:1px solid var(--vxw-border,#e7e3f3);box-shadow:0 18px 40px -16px rgba(24,12,55,.4);display:grid;gap:8px;white-space:normal">
                                                <strong style="font-size:14px">{{ $r['difference'] > 0 ? 'Pay' : 'Take back' }} {{ $money(abs($r['difference'])) }} on run {{ $target['label'] }}</strong>
                                                <span class="vxw-meta">Adds an adjustment line for {{ $r['streamer'] }} with what changed. Recalculating the run keeps it.</span>
                                                <label class="vxw-meta" for="note-{{ $r['show_id'] }}-{{ $r['streamer_id'] }}">Note (optional)</label>
                                                <textarea id="note-{{ $r['show_id'] }}-{{ $r['streamer_id'] }}" x-model="note" rows="2" maxlength="500" class="vxw-input" style="width:100%;min-height:56px"></textarea>
                                                <div style="display:flex;gap:8px;justify-content:flex-end">
                                                    <button type="button" class="vxw-btn vxw-btn--sm vxw-btn--ghost" x-on:click="open = false">Cancel</button>
                                                    <button type="button" class="vxw-btn vxw-btn--sm vxw-btn--primary" x-on:click="$wire.settle({{ $r['show_id'] }}, {{ $r['streamer_id'] }}, note); open = false" wire:loading.attr="disabled">Add to run</button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="vxw-meta">No draft run yet</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="vxw-meta" style="padding:12px 18px;margin:0">Positive means the person is owed more than they were paid; negative means they were paid more than the show now works out to. Lines overridden by hand on a run are not checked. "Carry to next run" adds the difference to the person's next draft run as an adjustment with a note; once added, the line drops off this list.</p>
        @endif
    </section>
</div>
</x-reports.shell>
</x-filament-panels::page>
