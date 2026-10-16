<x-filament-panels::page>
@php
    $sim = $this->simulation();
    $labels = $this->fieldLabels();
    $typeLabels = \App\Models\Streamer::payoutTypeLabels();
    $money = fn ($v) => ($v < 0 ? '−$' : '$') . number_format(abs((float) $v), 2);
    $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '±')) . '$' . number_format(abs((float) $v), 2);
    $fmt = function (string $field, $v) use ($money, $typeLabels) {
        if ($v === null || $v === '') return '—';
        return match (true) {
            in_array($field, ['gross_revenue', 'whatnot_net', 'tips', 'product_cost', 'package_rate', 'hourly_rate', 'pwe_rate', 'label_rate'], true) => $money($v),
            in_array($field, ['house_cut_override', 'payout_percentage', 'house_cut_percentage'], true) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%',
            $field === 'hours_streamed' => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . ' hrs',
            $field === 'include_tips' => $v ? 'Yes' : 'No',
            $field === 'payout_type' => $typeLabels[$v] ?? $v,
            $field === 'sales_split_basis' => \App\Models\Streamer::salesSplitBasisLabels()[$v] ?? $v,
            $field === 'burden_rate_type' => $v === 'percentage' ? '% of net' : 'Flat $ per show',
            default => is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') : $v,
        };
    };
    $deltaClass = fn ($v) => $v > 0.004 ? 'is-up' : ($v < -0.004 ? 'is-down' : 'is-flat');
    $editCount = $sim['edited_shows'] + $sim['edited_people'];
    $showFields = [...\App\Services\PayrollSimulation::SHOW_FIELDS, ...\App\Services\PayrollSimulation::REPORT_FIELDS];
    $realPeople = collect($sim['people'])->keyBy('id');
@endphp

<div class="vxsim space-y-4">
    {{-- Period and filters --}}
    <section class="vx-card vxsim-pad">
        <div class="vxsim-head">
            <div class="min-w-0">
                <div class="vxsim-kicker">Payroll Simulator</div>
                <h2 class="vxsim-title">What would these shows pay?</h2>
                <p class="vxsim-muted">Every number starts as the real one — the show, its report and each person's pay rates — and pay is worked out by the same calculation a pay run uses. Change anything to try it out.</p>
            </div>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="vxsim-btn">Back to Payroll</a>
        </div>
        <div class="vxsim-safe"><strong>What-if only.</strong> Nothing here is saved — no shows, reports, pay rates, payouts or pay runs change.</div>

        <div class="vxsim-filters">
            <div class="vxsim-period">
                <label class="vxsim-field"><span>From</span><input type="date" wire:model.live="from" class="vxsim-input"></label>
                <label class="vxsim-field"><span>To</span><input type="date" wire:model.live="to" class="vxsim-input"></label>
            </div>
            <div class="vxsim-pick"><x-vx-picker label="Business" all-label="All businesses" model="businessId" :options="$this->businessOptions()" :current="$businessId" icon="business" /></div>
            <div class="vxsim-pick"><x-vx-picker label="Person" all-label="Everyone" model="streamerId" :options="$this->streamerOptions()" :current="$streamerId" icon="person" /></div>
        </div>
        <div class="vxsim-chips">
            <button type="button" wire:click="setPeriod('last')" class="vxsim-chip">Last pay period</button>
            <button type="button" wire:click="setPeriod('this')" class="vxsim-chip">This pay period</button>
            <button type="button" wire:click="setPeriod('four')" class="vxsim-chip">Last 4 weeks</button>
            @if($editCount > 0 || count($excluded) > 0)
                <button type="button" wire:click="resetAll" class="vxsim-chip is-reset">Reset everything to real</button>
            @endif
        </div>
        @if($matching > count($showIds))
            <p class="vxsim-note">Showing the first {{ count($showIds) }} of {{ $matching }} shows in this period — narrow the dates, business or person to see the rest.</p>
        @endif
    </section>

    {{-- Totals --}}
    <section class="vxsim-tiles" wire:loading.class="opacity-60">
        <div class="vxsim-tile">
            <label>Total pay</label>
            <strong class="text-violet">{{ $money($sim['total']) }}</strong>
            @if(abs($sim['delta']) > 0.004)<span class="vxsim-delta {{ $deltaClass($sim['delta']) }}">{{ $signed($sim['delta']) }} vs real {{ $money($sim['real_total']) }}</span>@else<span class="vxsim-sub">{{ count($sim['rows']) }} {{ \Illuminate\Support\Str::plural('show', count($sim['rows'])) }} · {{ count($sim['people']) }} {{ \Illuminate\Support\Str::plural('person', count($sim['people'])) }}</span>@endif
        </div>
        <div class="vxsim-tile">
            <label>Whatnot net</label>
            <strong>{{ $money($sim['transfer']['net']) }}</strong>
            @if(abs($sim['transfer']['net'] - $sim['real_transfer']['net']) > 0.004)<span class="vxsim-delta {{ $deltaClass($sim['transfer']['net'] - $sim['real_transfer']['net']) }}">{{ $signed($sim['transfer']['net'] - $sim['real_transfer']['net']) }} vs real</span>@endif
        </div>
        <div class="vxsim-tile">
            <label>Product cost</label>
            <strong>{{ $money($sim['transfer']['product_cost']) }}</strong>
            @if(abs($sim['transfer']['product_cost'] - $sim['real_transfer']['product_cost']) > 0.004)<span class="vxsim-delta {{ $deltaClass($sim['transfer']['product_cost'] - $sim['real_transfer']['product_cost']) }}">{{ $signed($sim['transfer']['product_cost'] - $sim['real_transfer']['product_cost']) }} vs real</span>@endif
        </div>
        <div class="vxsim-tile">
            <label>Business transfer</label>
            <strong class="{{ $sim['transfer']['transfer'] < 0 ? 'text-bad' : 'text-good' }}">{{ $money($sim['transfer']['transfer']) }}</strong>
            @if(abs($sim['transfer']['transfer'] - $sim['real_transfer']['transfer']) > 0.004)<span class="vxsim-delta {{ $deltaClass($sim['transfer']['transfer'] - $sim['real_transfer']['transfer']) }}">{{ $signed($sim['transfer']['transfer'] - $sim['real_transfer']['transfer']) }} vs real</span>@else<span class="vxsim-sub">Product cost + net − pay (tips aside)</span>@endif
        </div>
    </section>

    {{-- People --}}
    <section class="vx-card vxsim-pad">
        <div class="vxsim-sechead">
            <div><h3 class="vxsim-h3">Pay by person</h3><p class="vxsim-muted">Rates are the ones payroll uses — team defaults plus any individual adjustments. Changing them here applies to that person's shows in this view only.</p></div>
        </div>
        @if($sim['people'] === [])
            <p class="vxsim-empty">No one is assigned to a show in this period.</p>
        @endif
        <div class="vxsim-people">
            @foreach($sim['people'] as $person)
                <div class="vxsim-person {{ $person['edited'] ? 'is-edited' : '' }}" wire:key="sim-person-{{ $person['id'] }}">
                    <div class="vxsim-person-top">
                        <div class="min-w-0">
                            <div class="vxsim-name">{{ $person['name'] }} @if($person['edited'])<span class="vxsim-badge">edited</span>@endif</div>
                            <div class="vxsim-sub">{{ $typeLabels[$person['payout_type']] ?? 'No pay type' }} · {{ $person['shows'] }} {{ \Illuminate\Support\Str::plural('show', $person['shows']) }}</div>
                        </div>
                        <div class="text-right">
                            <div class="vxsim-amount">{{ $money($person['total']) }}</div>
                            @if(abs($person['delta']) > 0.004)<div class="vxsim-delta {{ $deltaClass($person['delta']) }}">{{ $signed($person['delta']) }} vs {{ $money($person['real_total']) }}</div>@endif
                        </div>
                    </div>
                    @if($person['errors'] > 0)<div class="vxsim-error">{{ $person['errors'] }} {{ \Illuminate\Support\Str::plural('show', $person['errors']) }} could not be calculated — see below.</div>@endif
                    <div class="vxsim-actions">
                        <button type="button" wire:click="editStreamer({{ $person['id'] }})" class="vxsim-btn is-primary">Edit pay</button>
                        @if($person['edited'])<button type="button" wire:click="resetStreamer({{ $person['id'] }})" class="vxsim-btn">Reset to real</button>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Shows --}}
    <section class="vx-card overflow-hidden">
        <div class="vxsim-pad vxsim-sechead">
            <div><h3 class="vxsim-h3">Shows</h3><p class="vxsim-muted">The real shows in this period, each with the figures payroll reads. Edit a show to change its numbers, or leave it out.</p></div>
        </div>
        @if($sim['rows'] === [] && $sim['excluded'] === [])
            <p class="vxsim-empty vxsim-pad">No shows in this period{{ $businessId || $streamerId ? ' for this filter' : '' }}.</p>
        @endif
        @foreach($sim['rows'] as $row)
            @php $edited = $row['edits'] !== []; @endphp
            <article class="vxsim-show {{ $edited ? 'is-edited' : '' }}" wire:key="sim-show-{{ $row['id'] }}">
                <div class="vxsim-show-top">
                    <div class="min-w-0">
                        <div class="vxsim-sub">{{ $row['date']?->format('D M j') }}@if($row['channel']) · {{ $row['channel'] }}@endif @unless($row['has_report']) · <span class="text-warn">no show report</span>@endunless</div>
                        <div class="vxsim-name">{{ $row['title'] }} @if($edited)<span class="vxsim-badge">edited</span>@endif</div>
                    </div>
                    <div class="text-right">
                        <div class="vxsim-amount">{{ $money($row['total']) }}</div>
                        @if(abs($row['delta']) > 0.004)<div class="vxsim-delta {{ $deltaClass($row['delta']) }}">{{ $signed($row['delta']) }} vs {{ $money($row['real_total']) }}</div>@endif
                    </div>
                </div>

                <dl class="vxsim-figs">
                    @foreach(['whatnot_net', 'gross_revenue', 'product_cost', 'hours_streamed', 'number_of_shipments', 'pwe_count', 'label_count', 'tips', 'house_cut_override'] as $f)
                        @php $isEd = array_key_exists($f, $row['edits']); @endphp
                        @continue(! $isEd && $f === 'house_cut_override' && $row['real'][$f] === null)
                        <div class="{{ $isEd ? 'is-edited' : '' }}">
                            <dt>{{ $labels[$f] }}</dt>
                            <dd>{{ $fmt($f, $row['inputs'][$f]) }}@if($isEd)<s>{{ $fmt($f, $row['real'][$f]) }}</s>@endif</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="vxsim-lines">
                    @forelse($row['streamers'] as $s)
                        <div class="vxsim-line" wire:key="sim-line-{{ $row['id'] }}-{{ $s['id'] }}">
                            <div class="vxsim-line-top">
                                <div class="min-w-0"><span class="font-semibold">{{ $s['name'] }}</span>@if(count($row['streamers']) > 1 && $s['is_primary'])<span class="vxsim-tag">primary</span>@endif @if($s['edited'])<span class="vxsim-badge">rates edited</span>@endif</div>
                                <div class="text-right"><span class="font-bold">{{ $money($s['total']) }}</span>@if(abs($s['delta']) > 0.004) <span class="vxsim-delta {{ $deltaClass($s['delta']) }}">{{ $signed($s['delta']) }}</span>@endif</div>
                            </div>
                            @if($s['error'])
                                <div class="vxsim-error">Can't calculate: {{ $s['error'] }}</div>
                            @endif
                            @foreach($s['lines'] as $line)
                                <details class="vxsim-detail">
                                    <summary><span>{{ $line['label'] }}</span><span>{{ $money($line['amount']) }}</span></summary>
                                    <p>{{ $line['notes'] }}</p>
                                </details>
                            @endforeach
                            @if($s['recorded'])
                                <div class="vxsim-sub mt-1">On a pay run now: {{ $money($s['recorded']['amount']) }} ({{ $s['recorded']['state'] }})</div>
                            @endif
                        </div>
                    @empty
                        <div class="vxsim-sub">No one is assigned to this show, so there is no pay to work out.</div>
                    @endforelse
                </div>

                <div class="vxsim-actions">
                    <button type="button" wire:click="editShow({{ $row['id'] }})" class="vxsim-btn is-primary">Edit numbers</button>
                    @if($edited)<button type="button" wire:click="resetShow({{ $row['id'] }})" class="vxsim-btn">Reset to real</button>@endif
                    <button type="button" wire:click="toggleShow({{ $row['id'] }})" class="vxsim-btn is-quiet">Leave out</button>
                </div>
            </article>
        @endforeach
        @foreach($sim['excluded'] as $row)
            <div class="vxsim-show is-out" wire:key="sim-out-{{ $row['id'] }}">
                <div class="min-w-0"><div class="vxsim-sub">{{ $row['date']?->format('D M j') }} · left out</div><div class="vxsim-name">{{ $row['title'] }}</div><div class="vxsim-sub">{{ $row['streamers'] ?: 'No one assigned' }}</div></div>
                <button type="button" wire:click="toggleShow({{ $row['id'] }})" class="vxsim-btn">Put back</button>
            </div>
        @endforeach
    </section>
</div>

{{-- Show editor --}}
<x-filament::modal id="sim-show-editor" width="2xl">
    @if($editingShowId && isset($showReal[$editingShowId]))
        @php
            $row = collect($sim['rows'])->firstWhere('id', $editingShowId);
            $eds = $this->showEdits($editingShowId);
        @endphp
        <x-slot name="heading">{{ $row['title'] ?? 'Show' }}</x-slot>
        <x-slot name="description">Change any figure to see what it would pay. Blank means not entered — the calculation then does what payroll does (hours from the clock, shipments from the shipment list).</x-slot>
        <div class="vxsim vxsim-grid">
            @foreach($showFields as $f)
                @php $isEd = array_key_exists($f, $eds); @endphp
                <label class="vxsim-field {{ $isEd ? 'is-edited' : '' }}" wire:key="sim-edit-{{ $editingShowId }}-{{ $f }}">
                    <span>{{ $labels[$f] }} @if($isEd)<em class="vxsim-badge">edited</em>@endif</span>
                    <input type="number" inputmode="decimal" step="{{ in_array($f, ['number_of_shipments', 'pwe_count', 'label_count'], true) ? '1' : '0.01' }}" min="0" class="vxsim-input"
                           wire:model.live.debounce.400ms="showInputs.{{ $editingShowId }}.{{ $f }}" placeholder="Not entered">
                    <small>Real: {{ $fmt($f, $showReal[$editingShowId][$f]) }} @if($isEd)<button type="button" wire:click="resetField('show', {{ $editingShowId }}, '{{ $f }}')">reset</button>@endif</small>
                </label>
            @endforeach
        </div>
        @if($row)
            <div class="vxsim vxsim-modal-total">
                <span>This show pays</span><strong>{{ $money($row['total']) }}</strong>
                @if(abs($row['delta']) > 0.004)<span class="vxsim-delta {{ $deltaClass($row['delta']) }}">{{ $signed($row['delta']) }} vs real</span>@endif
            </div>
        @endif
        <x-slot name="footerActions">
            @if($eds !== [])<x-filament::button color="gray" wire:click="resetShow({{ $editingShowId }})">Reset show to real</x-filament::button>@endif
            <x-filament::button x-on:click="$dispatch('close-modal', { id: 'sim-show-editor' })">Done</x-filament::button>
        </x-slot>
    @endif
</x-filament::modal>

{{-- Rate editor --}}
<x-filament::modal id="sim-rate-editor" width="2xl">
    @if($editingStreamerId && isset($rateReal[$editingStreamerId]))
        @php
            $person = $realPeople->get($editingStreamerId);
            $eds = $this->rateEdits($editingStreamerId);
            $type = $rateInputs[$editingStreamerId]['payout_type'] ?? null;
        @endphp
        <x-slot name="heading">{{ $person['name'] ?? 'Pay rates' }}</x-slot>
        <x-slot name="description">The rates payroll uses for this person. Changes apply to their {{ $person['shows'] ?? 0 }} {{ \Illuminate\Support\Str::plural('show', $person['shows'] ?? 0) }} in this view and are never saved.</x-slot>
        <div class="vxsim space-y-3">
            <div class="vxsim-field {{ array_key_exists('payout_type', $eds) ? 'is-edited' : '' }}">
                <span>Pay type @if(array_key_exists('payout_type', $eds))<em class="vxsim-badge">edited</em>@endif</span>
                <x-vx-picker label="Pay type" model="rateInputs.{{ $editingStreamerId }}.payout_type" :options="$this->payTypeOptions()" :current="$type" icon="pay" />
                <small>Real: {{ $fmt('payout_type', $rateReal[$editingStreamerId]['payout_type']) }} @if(array_key_exists('payout_type', $eds))<button type="button" wire:click="resetField('rate', {{ $editingStreamerId }}, 'payout_type')">reset</button>@endif</small>
            </div>
            <div class="vxsim-grid">
                @foreach($this->rateFieldsFor($type) as $f)
                    @php $isEd = array_key_exists($f, $eds); @endphp
                    <label class="vxsim-field {{ $isEd ? 'is-edited' : '' }} {{ $f === 'custom_payout_formula' ? 'is-wide' : '' }}" wire:key="sim-rate-{{ $editingStreamerId }}-{{ $f }}">
                        <span>{{ $labels[$f] }} @if($isEd)<em class="vxsim-badge">edited</em>@endif</span>
                        @if($f === 'include_tips')
                            <span class="vxsim-switch"><input type="checkbox" wire:model.live="rateInputs.{{ $editingStreamerId }}.include_tips"> Add the show's tips to this person's pay</span>
                        @elseif($f === 'burden_rate_type')
                            <span class="vxsim-seg">
                                @foreach(['' => 'None', 'flat' => 'Flat $', 'percentage' => '% of net'] as $v => $l)
                                    <button type="button" wire:click="$set('rateInputs.{{ $editingStreamerId }}.burden_rate_type', {{ $v === '' ? 'null' : "'{$v}'" }})" class="{{ ($rateInputs[$editingStreamerId]['burden_rate_type'] ?? null) == ($v ?: null) ? 'is-on' : '' }}">{{ $l }}</button>
                                @endforeach
                            </span>
                        @elseif($f === 'sales_split_basis')
                            <span class="vxsim-seg">
                                @foreach(\App\Models\Streamer::salesSplitBasisLabels() as $v => $l)
                                    <button type="button" wire:click="$set('rateInputs.{{ $editingStreamerId }}.sales_split_basis', '{{ $v }}')" class="{{ ($rateInputs[$editingStreamerId]['sales_split_basis'] ?? 'net') === $v ? 'is-on' : '' }}">{{ $l }}</button>
                                @endforeach
                            </span>
                        @elseif($f === 'custom_payout_formula')
                            <input type="text" class="vxsim-input" wire:model.live.debounce.600ms="rateInputs.{{ $editingStreamerId }}.custom_payout_formula" placeholder="e.g. whatnot_net * 0.1 + tip_share">
                        @else
                            <input type="number" inputmode="decimal" step="0.01" min="0" class="vxsim-input" wire:model.live.debounce.400ms="rateInputs.{{ $editingStreamerId }}.{{ $f }}" placeholder="0">
                        @endif
                        <small>Real: {{ $fmt($f, $rateReal[$editingStreamerId][$f]) }} @if($isEd)<button type="button" wire:click="resetField('rate', {{ $editingStreamerId }}, '{{ $f }}')">reset</button>@endif</small>
                    </label>
                @endforeach
            </div>
            @if($person)
                <div class="vxsim-modal-total">
                    <span>Pays across these shows</span><strong>{{ $money($person['total']) }}</strong>
                    @if(abs($person['delta']) > 0.004)<span class="vxsim-delta {{ $deltaClass($person['delta']) }}">{{ $signed($person['delta']) }} vs real</span>@endif
                </div>
            @endif
        </div>
        <x-slot name="footerActions">
            @if($eds !== [])<x-filament::button color="gray" wire:click="resetStreamer({{ $editingStreamerId }})">Reset rates to real</x-filament::button>@endif
            <x-filament::button x-on:click="$dispatch('close-modal', { id: 'sim-rate-editor' })">Done</x-filament::button>
        </x-slot>
    @endif
</x-filament::modal>

<style>
.vxsim{--sim-v:#7c3aed;--sim-v-soft:#f5f3ff;--sim-v-line:#ddd6fe;--sim-muted:var(--vx-muted,#6b7280);--sim-line:var(--vx-border,#e5e7eb);--sim-surface:var(--vx-surface,#fff)}
.dark .vxsim{--sim-v:#a78bfa;--sim-v-soft:rgba(124,58,237,.14);--sim-v-line:rgba(167,139,250,.4);--sim-line:#1f2937;--sim-surface:#111827}
.vxsim-pad{padding:16px}
@media(min-width:640px){.vxsim-pad{padding:20px}}
.vxsim-head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between}
.vxsim-kicker{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--sim-v)}
.vxsim-title{margin-top:2px;font-size:1.25rem;font-weight:700;color:inherit}
.vxsim-h3{font-size:.875rem;font-weight:700}
.vxsim-muted{margin-top:4px;max-width:48rem;font-size:12px;line-height:1.5;color:var(--sim-muted)}
.vxsim-sub{font-size:11px;color:var(--sim-muted)}
.vxsim-note{margin-top:10px;font-size:12px;color:#b45309}
.vxsim-empty{font-size:13px;color:var(--sim-muted);padding-top:8px}
.vxsim-safe{margin-top:12px;padding:10px 12px;border-radius:10px;background:var(--sim-v-soft);border:1px solid var(--sim-v-line);font-size:12px}
.vxsim-filters{margin-top:14px;display:grid;gap:10px;grid-template-columns:1fr}
@media(min-width:768px){.vxsim-filters{grid-template-columns:minmax(0,1.3fr) minmax(0,1fr) minmax(0,1fr)}}
.vxsim-period{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.vxsim-field{display:flex;flex-direction:column;gap:4px;min-width:0}
.vxsim-field>span{font-size:11px;font-weight:700;color:var(--sim-muted);display:flex;align-items:center;gap:6px}
.vxsim-field small{font-size:11px;color:var(--sim-muted)}
.vxsim-field small button{margin-left:6px;color:var(--sim-v);font-weight:700;text-decoration:underline}
.vxsim-field.is-wide{grid-column:1/-1}
.vxsim-input{width:100%;min-height:42px;border-radius:10px;border:1px solid var(--sim-line);background:var(--sim-surface);padding:0 12px;font-size:14px;color:inherit}
.vxsim-input:focus{outline:none;border-color:var(--sim-v);box-shadow:0 0 0 3px rgba(139,92,246,.25)}
.vxsim-field.is-edited .vxsim-input,.vxsim-field.is-edited .vx-picker-btn{border-color:var(--sim-v);background:var(--sim-v-soft)}
.vxsim-pick{display:flex;align-items:flex-end}
.vxsim-pick .vx-picker-btn{min-height:42px}
.vxsim-chips{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
.vxsim-chip{min-height:36px;padding:0 12px;border-radius:999px;border:1px solid var(--sim-line);font-size:12px;font-weight:600}
.vxsim-chip:hover{border-color:var(--sim-v);color:var(--sim-v)}
.vxsim-chip.is-reset{border-color:var(--sim-v-line);background:var(--sim-v-soft);color:var(--sim-v)}
.vxsim-tiles{display:grid;gap:10px;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(min-width:1024px){.vxsim-tiles{grid-template-columns:repeat(4,minmax(0,1fr))}}
.vxsim-tile{display:flex;flex-direction:column;gap:2px;min-width:0;padding:14px;border-radius:14px;border:1px solid var(--sim-line);background:var(--sim-surface)}
.vxsim-tile label{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--sim-muted)}
.vxsim-tile strong{font-size:clamp(1rem,4.6vw,1.25rem);font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:440px){.vxsim-period{grid-template-columns:1fr}}
.text-violet{color:var(--sim-v)}.text-good{color:#059669}.text-bad{color:#dc2626}.text-warn{color:#b45309}
.vxsim-delta{display:inline-block;font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}
.vxsim-delta.is-up{color:#059669}.vxsim-delta.is-down{color:#dc2626}.vxsim-delta.is-flat{color:var(--sim-muted)}
.vxsim-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:999px;background:var(--sim-v-soft);border:1px solid var(--sim-v-line);color:var(--sim-v);font-size:10px;font-weight:700;font-style:normal;letter-spacing:.02em;vertical-align:middle}
.vxsim-tag{margin-left:6px;padding:1px 6px;border-radius:6px;background:rgba(107,114,128,.12);font-size:10px;font-weight:700;color:var(--sim-muted)}
.vxsim-sechead{display:flex;justify-content:space-between;gap:12px}
.vxsim-people{margin-top:12px;display:grid;gap:10px;grid-template-columns:1fr}
@media(min-width:640px){.vxsim-people{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1280px){.vxsim-people{grid-template-columns:repeat(3,minmax(0,1fr))}}
.vxsim-person{padding:12px;border-radius:12px;border:1px solid var(--sim-line)}
.vxsim-person.is-edited,.vxsim-show.is-edited{border-left:3px solid var(--sim-v)}
.vxsim-person-top,.vxsim-show-top,.vxsim-line-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.vxsim-name{font-size:14px;font-weight:700;overflow-wrap:anywhere}
.vxsim-amount{font-size:1.05rem;font-weight:800;color:var(--sim-v);font-variant-numeric:tabular-nums;white-space:nowrap}
.vxsim-actions{margin-top:10px;display:flex;flex-wrap:wrap;gap:8px}
.vxsim-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 12px;border-radius:10px;border:1px solid var(--sim-line);font-size:12px;font-weight:600;white-space:nowrap}
.vxsim-btn:hover{border-color:var(--sim-v)}
.vxsim-btn.is-primary{border-color:var(--sim-v-line);background:var(--sim-v-soft);color:var(--sim-v)}
.vxsim-btn.is-quiet{border-color:transparent;color:var(--sim-muted)}
.vxsim-show{padding:14px 16px;border-top:1px solid var(--sim-line)}
@media(min-width:640px){.vxsim-show{padding:16px 20px}}
.vxsim-show.is-out{display:flex;justify-content:space-between;align-items:center;gap:12px;opacity:.65}
.vxsim-figs{margin-top:10px;display:grid;gap:6px;grid-template-columns:repeat(2,minmax(0,1fr))}
@media(min-width:640px){.vxsim-figs{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(min-width:1280px){.vxsim-figs{grid-template-columns:repeat(8,minmax(0,1fr))}}
.vxsim-figs>div{padding:6px 8px;border-radius:8px;background:rgba(107,114,128,.07);min-width:0}
.vxsim-figs>div.is-edited{background:var(--sim-v-soft);box-shadow:inset 0 0 0 1px var(--sim-v-line)}
.vxsim-figs dt{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sim-muted)}
.vxsim-figs dd{font-size:13px;font-weight:700;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.vxsim-figs dd s{margin-left:6px;font-size:11px;font-weight:500;color:var(--sim-muted)}
.vxsim-figs .is-edited dd{color:var(--sim-v)}
.vxsim-lines{margin-top:10px;display:grid;gap:8px}
.vxsim-line{padding:10px;border-radius:10px;border:1px solid var(--sim-line);font-size:13px}
.vxsim-detail{margin-top:6px;font-size:12px}
.vxsim-detail summary{display:flex;justify-content:space-between;gap:8px;cursor:pointer;list-style:none;color:var(--sim-muted)}
.vxsim-detail summary::-webkit-details-marker{display:none}
.vxsim-detail summary span:first-child::after{content:' ▾';font-size:10px}
.vxsim-detail summary span:last-child{font-variant-numeric:tabular-nums;color:inherit}
.vxsim-detail p{margin-top:4px;padding:8px;border-radius:8px;background:rgba(107,114,128,.07);font-size:11px;line-height:1.5;color:var(--sim-muted);overflow-wrap:anywhere}
.vxsim-error{margin-top:6px;padding:6px 8px;border-radius:8px;background:rgba(220,38,38,.08);color:#b91c1c;font-size:12px}
.vxsim-grid{display:grid;gap:12px;grid-template-columns:1fr}
@media(min-width:560px){.vxsim-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:768px){.vxsim-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.vxsim-modal-total{margin-top:14px;display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;padding:10px 12px;border-radius:10px;background:var(--sim-v-soft);font-size:13px}
.vxsim-modal-total strong{font-size:1.1rem;color:var(--sim-v)}
.vxsim-seg{display:inline-flex;flex-wrap:wrap;gap:4px;padding:3px;border-radius:10px;border:1px solid var(--sim-line)}
.vxsim-seg button{min-height:34px;padding:0 10px;border-radius:8px;font-size:12px;font-weight:600;color:var(--sim-muted)}
.vxsim-seg button.is-on{background:var(--sim-v-soft);color:var(--sim-v);box-shadow:inset 0 0 0 1px var(--sim-v-line)}
.vxsim-switch{display:flex!important;align-items:center;gap:8px;min-height:42px;font-weight:500!important;color:inherit!important}
.vxsim-switch input{width:18px;height:18px;accent-color:#7c3aed}
</style>
</x-filament-panels::page>
