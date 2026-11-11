<x-filament-panels::page>
@php
    $money = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
    $missing = $rows->whereNull('legal_name')->count();
@endphp
<div class="vxw" data-vx-page="year-end-pay">
    <section class="vxw-card">
        <div class="vxw-card-body" style="display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end;justify-content:space-between">
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
                <label class="vxw-meta" for="ye-year" style="display:grid;gap:4px">Year
                    <select id="ye-year" class="vxw-input" wire:model.live="year">@foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select>
                </label>
                <label class="vxw-meta" for="ye-min" style="display:grid;gap:4px">Only people paid at least
                    <input id="ye-min" class="vxw-input" type="text" inputmode="decimal" placeholder="$0" wire:model.live.debounce.500ms="minimum" style="width:140px">
                </label>
            </div>
            <x-report-toolbar :exports="['export' => 'Export CSV']" />
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">People</div><div class="vxw-stat-value">{{ $rows->count() }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Total paid in {{ $year }}</div><div class="vxw-stat-value">{{ $money($rows->sum('total')) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Missing a legal name</div><div class="vxw-stat-value {{ $missing ? 'vxw-tone-warn' : 'vxw-tone-ok' }}">{{ $missing }}</div></div>
        </div>
    </section>

    <section class="vxw-card" style="margin-top:16px">
        @if ($rows->isEmpty())
            <p class="vxw-meta" style="padding:18px;margin:0">No pay was marked paid in {{ $year }}{{ $minimum ? ' at or above that amount' : '' }}. Runs count once they are marked paid.</p>
        @else
            <div style="overflow-x:auto">
                <table class="vxw-table" style="width:100%">
                    <thead><tr>
                        <th scope="col" style="text-align:left">Person</th><th scope="col" style="text-align:left">Business</th>
                        <th scope="col" style="text-align:right">Total paid</th><th scope="col" style="text-align:right">Tips included</th><th scope="col" style="text-align:right">Other pay</th><th scope="col" style="text-align:right">Deductions</th>
                        <th scope="col" style="text-align:right">Runs</th><th scope="col" style="text-align:left">Paid between</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr wire:key="ye-{{ $r['streamer_id'] }}">
                                <td><strong>{{ $r['name'] }}</strong>
                                    <div class="vxw-meta">{{ $r['legal_name'] ? 'Legal name: ' . $r['legal_name'] : '' }}@unless($r['legal_name'])<span style="color:#b45309">No legal name on file</span>@endunless · {{ $r['kind'] }}</div>
                                    @if ($r['email'])<div class="vxw-meta">{{ $r['email'] }}</div>@endif
                                </td>
                                <td>{{ $r['business'] ?? '—' }}</td>
                                <td style="text-align:right;font-weight:700;font-variant-numeric:tabular-nums">{{ $money($r['total']) }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($r['tips']) }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($r['other']) }}</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ $money($r['deductions']) }}</td>
                                <td style="text-align:right">{{ $r['runs'] }}</td>
                                <td class="vxw-meta" style="white-space:nowrap">{{ $r['first_paid'] ? \Illuminate\Support\Carbon::parse($r['first_paid'])->format('M j') : '' }} – {{ $r['last_paid'] ? \Illuminate\Support\Carbon::parse($r['last_paid'])->format('M j') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="vxw-meta" style="padding:12px 18px;margin:0">A run counts in the year it was marked paid. Total paid already includes tips and other pay and has deductions taken off. Add missing legal names on each person's profile before filing.</p>
        @endif
    </section>
</div>
</x-filament-panels::page>
