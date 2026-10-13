<x-filament-panels::page>
@php
    $pct=(float)($streamer['payout_percentage']??0);
    $ship=(float)$streamer_burden_per_shipment;
    $hour=(float)$streamer_burden_per_hour;
    $editingMember=$editing_member_id ? \App\Models\Streamer::find($editing_member_id) : null;
    $business=$this->business;
    $isSplit=($streamer['payout_type']??'profit_share')==='sales_split';
    $cut=(float)($streamer['house_cut_percentage']??0);
    $basisLabel=($streamer['sales_split_basis']??'net')==='gross'?'Gross Sales':'Estimated Net Earnings';
@endphp
<div class="space-y-6">
    <div class="vx-card p-4 text-sm" style="border-left:4px solid {{ $business?->color ?? '#9ca3af' }}">
        @if($business)
            <strong>Rates for {{ $business->name }}.</strong>
            @if($this->usesSharedRates)
                It hasn't saved its own rates yet, so its people are paid on the shared rates shown below. Change anything and save to give {{ $business->label() }} its own.
            @else
                Only {{ $business->label() }}'s people are paid on these.
                <button type="button" wire:click="useSharedRates" wire:confirm="Drop {{ $business->name }}'s own rates and use the shared ones?" class="ml-1 font-bold text-primary-600 underline">Use the shared rates instead</button>
            @endif
        @else
            <strong>Shared rates.</strong> Used by any business that hasn't saved its own. Pick a business in the <strong>Business</strong> picker at the top to set rates for just that business (for example, Estill Distribution's sales split).
        @endif
        <span class="text-gray-500">Burden rates, the pay schedule and automation are the same for every business.</span>
    </div>

    <div class="vx-card p-5">
        <p class="text-xs font-bold uppercase tracking-[.16em] text-primary-500">How streamers are paid{{ $business ? ' · '.$business->name : '' }}</p>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <label class="flex cursor-pointer gap-3 rounded-[10px] border p-4 {{ ! $isSplit ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-[var(--vx-border)]' }}">
                <input type="radio" wire:model.live="streamer.payout_type" value="profit_share" class="mt-1">
                <span><span class="block font-bold">Profit share</span><span class="block text-xs text-gray-500">A % of the show's profit after product cost and burden, plus hourly for in-house streamers. How Vortex Collects pays.</span></span>
            </label>
            <label class="flex cursor-pointer gap-3 rounded-[10px] border p-4 {{ $isSplit ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-[var(--vx-border)]' }}">
                <input type="radio" wire:model.live="streamer.payout_type" value="sales_split" class="mt-1">
                <span><span class="block font-bold">Sales split</span><span class="block text-xs text-gray-500">The business keeps a % of what the streamer's shows earned; the streamer gets the rest. How Estill Distribution pays.</span></span>
            </label>
        </div>
        @if($isSplit)
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                    <span class="text-xs font-semibold text-gray-500">The business keeps</span>
                    <div class="mt-2 flex items-center gap-2"><input wire:model.live.debounce.300ms="streamer.house_cut_percentage" type="number" step=".01" min="0" max="100" class="w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"><span class="font-bold">%</span></div>
                    @error('streamer.house_cut_percentage')<span class="mt-1 block text-xs text-danger-600">{{ $message }}</span>@enderror
                </label>
                <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                    <span class="text-xs font-semibold text-gray-500">Of the streamer's</span>
                    <select wire:model.live="streamer.sales_split_basis" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-bold dark:border-gray-600 dark:bg-gray-800">
                        @foreach(\App\Models\Streamer::salesSplitBasisLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50/60 p-5 font-mono text-sm dark:border-primary-900 dark:bg-primary-950/20">
                <div>Business keeps = {{ $basisLabel }} × {{ number_format($cut, 2) }}%</div>
                <div class="font-bold text-primary-700 dark:text-primary-300">Streamer Pay = {{ $basisLabel }} × {{ number_format(100 - $cut, 2) }}% @if($streamer['include_tips']??true) + Tips @endif</div>
                <p class="mt-2 font-sans text-xs text-gray-500">e.g. a show that earned $1,000 pays the streamer ${{ number_format(1000 * (100 - $cut) / 100, 2) }}. Anyone paid differently (like an hourly streamer) gets an individual override below.</p>
            </div>
        @endif
    </div>

    <div class="vx-card p-5" @if($isSplit) hidden @endif>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-primary-500">Standard payroll calculation</p>
                <h2 class="mt-1 text-xl font-bold">Streamer Pay Formula</h2>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">This is the calculation from the original Streamer Log spreadsheet. It is the default for every streamer in the simulator and real weekly Pay Runs unless that person has an explicit custom override.</p>
            </div>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">Weekly · default for everyone</span>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                <span class="text-xs font-semibold text-gray-500">Default Streamer Pay %</span>
                <div class="mt-2 flex items-center gap-2"><input wire:model.live.debounce.300ms="streamer.payout_percentage" type="number" step=".01" class="w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"><span class="font-bold">%</span></div>
            </label>
            <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                <span class="text-xs font-semibold text-gray-500">Burden Per Shipment</span>
                <div class="mt-2 flex items-center gap-2"><span class="font-bold">$</span><input wire:model.live.debounce.300ms="streamer_burden_per_shipment" type="number" step=".01" class="w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"></div>
            </label>
            <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                <span class="text-xs font-semibold text-gray-500">Burden Per Hour</span>
                <div class="mt-2 flex items-center gap-2"><span class="font-bold">$</span><input wire:model.live.debounce.300ms="streamer_burden_per_hour" type="number" step=".01" class="w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"></div>
            </label>
        </div>

        <label class="mt-4 block rounded-[10px] border border-[var(--vx-border)] p-4 md:max-w-sm">
            <span class="text-xs font-semibold text-gray-500">In-house hourly rate</span>
            <div class="mt-2 flex items-center gap-2"><span class="font-bold">$</span><input wire:model.live.debounce.300ms="streamer.hourly_rate" type="number" step=".01" min="0" placeholder="None" class="w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"><span class="text-sm font-bold">/hr</span></div>
            <span class="mt-2 block text-xs text-gray-500">Paid to in-house streamers for the hours on each show, on top of their share. Remote streamers are not paid hourly. Leave blank for none.</span>
        </label>

        <div class="mt-5 rounded-xl border border-primary-200 bg-primary-50/60 p-5 dark:border-primary-900 dark:bg-primary-950/20">
            <div class="text-xs font-bold uppercase tracking-wider text-primary-600">How payroll is calculated</div>
            <div class="mt-3 space-y-2 font-mono text-sm">
                <div>Product Cost = Σ (Inventory Cost × Quantity Sold)</div>
                <div>Burden = (Shipments × ${{number_format($ship,2)}}) + (Hours Worked × ${{number_format($hour,2)}})</div>
                <div>Net Revenue = Gross Revenue − Product Cost − Burden</div>
                <div class="font-bold text-primary-700 dark:text-primary-300">Streamer Pay = Net Revenue × {{number_format($pct,2)}}% @if($streamer['include_tips']??true) + Tips @endif</div>
                @if((float)($streamer['hourly_rate']??0) > 0)<div class="font-bold text-primary-700 dark:text-primary-300">In-house streamers also get Hours Worked × ${{number_format((float)$streamer['hourly_rate'],2)}}</div>@endif
            </div>
            <p class="mt-3 text-xs text-gray-500">A weekly Pay Run collects that week's eligible shows. The show calculations roll up into the person's weekly total; a custom override is only used when you intentionally set one.</p>
        </div>

        <details class="mt-5 rounded-[10px] border border-[var(--vx-border)] p-4">
            <summary class="cursor-pointer text-sm font-bold">Advanced: change the standard calculation for everyone</summary>
            <p class="mt-2 text-xs text-gray-500">Normally leave this blank. Use it only if the spreadsheet formula itself changes for the whole streamer team.</p>
            <textarea wire:model="streamer.custom_payout_formula" rows="3" class="mt-3 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-mono text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Optional team-wide custom formula"></textarea>
        </details>
    </div>

    <div class="vx-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold">Individual Overrides</h2>
                <p class="mt-1 text-sm text-gray-500">Filter the roster, then select a person only when their compensation should differ from the role default.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300"><input type="checkbox" wire:click="setShowArchived({{ $show_archived ? 'false' : 'true' }})" @checked($show_archived)> Show archived</label>
            <div class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
                <button wire:click="setMemberFilter('streamer')" class="rounded-lg px-4 py-2 text-sm font-bold {{$member_filter==='streamer'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-300':'text-gray-500'}}">Streamers</button>
                <button wire:click="setMemberFilter('fulfillment')" class="rounded-lg px-4 py-2 text-sm font-bold {{$member_filter==='fulfillment'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-300':'text-gray-500'}}">Fulfillment</button>
            </div>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($this->filteredMembers as $member)
                @php $pay=$member->effectiveCompensation(); @endphp
                <div class="relative" wire:key="member-{{ $member->id }}">
                <button wire:click="editMember({{$member->id}})" class="h-full w-full rounded-xl border border-gray-200 p-4 pb-10 text-left transition hover:border-primary-500 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3"><span class="font-bold">{{$member->name}}</span><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-bold uppercase text-gray-500 dark:bg-gray-800">{{$member->isFulfillment()?'Fulfillment':($member->isRemote()?'Remote':'In-house')}}</span></div>
                    @if($member->status === 'inactive' || ! $member->user_id)
                        <div class="mt-1 flex flex-wrap gap-1">
                            @if($member->status === 'inactive')<span class="rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-bold uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-200">Archived</span>@endif
                            @if(! $member->user_id)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-900/50 dark:text-amber-200" title="No user account is linked to this pay profile">No login</span>@endif
                        </div>
                    @endif
                    <div class="mt-1 text-xs text-gray-500">
                        @if(!$member->isFulfillment())@if(($pay['effective']['payout_type']??null)==='sales_split')Sales split · keeps {{number_format(100-(float)($pay['effective']['house_cut_percentage']??0),2)}}%@else{{number_format((float)($pay['effective']['payout_percentage']??0),2)}}%@endif @if($member->paidHourlyRate()) + ${{number_format($member->paidHourlyRate(),2)}}/hr @endif · @endif
                        @if(count($pay['overrides']??[])) Individual Override @else Standard {{ $member->isFulfillment() ? 'Fulfillment' : 'Streamer' }} Calculation @endif
                    </div>
                </button>
                {{-- Outside the card's own button: a button cannot hold another. --}}
                @if($member->status === 'inactive')
                    <button type="button" wire:click="restoreMember({{ $member->id }})" class="absolute bottom-2 right-2 inline-flex min-h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Put {{ $member->name }} back on payroll"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" aria-hidden="true" />Restore</button>
                @else
                    <button type="button" x-data x-on:click="if (await window.vxConfirm(@js('Remove '.$member->name.' from payroll? A profile with no shows or pay is deleted; one with history is archived and can be restored.'))) $wire.removeMember({{ $member->id }})" class="absolute bottom-2 right-2 inline-flex min-h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-danger-700 hover:bg-danger-50 dark:text-danger-300 dark:hover:bg-danger-950/40" aria-label="Remove {{ $member->name }} from payroll"><x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />Remove</button>
                @endif
                </div>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500">No {{$member_filter}} team members found.</div>
            @endforelse
        </div>

        @if($editingMember)
        <div class="mt-5 rounded-xl border-2 border-primary-300 p-5 dark:border-primary-800">
            <div class="flex items-center justify-between"><div><div class="text-xs font-bold uppercase text-primary-600">Custom override</div><div class="text-lg font-bold">{{$editingMember->name}}</div><div class="text-xs text-gray-500">Weekly payroll · {{$editingMember->isFulfillment()?'Fulfillment':'Streamer'}}</div></div><button wire:click="closeMemberEditor" class="text-sm text-gray-500">Close</button></div>

            @if(!$editingMember->isFulfillment())
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="rounded-lg border p-3 md:col-span-2"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model.live="member_override_enabled.payout_type">Paid a different way from {{ $editingMember->business_id ? (\App\Models\Business::whereKey($editingMember->business_id)->value('name') ?? 'the team') : 'the team' }}</span>
                        <select wire:model.live="member_override_values.payout_type" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800">
                            <option value="profit_share">Profit share (+ hourly if in-house) — like Vortex Collects</option>
                            <option value="sales_split">Sales split — the business keeps a %</option>
                        </select>
                        <span class="mt-1 block text-xs text-gray-500">For example, Estill's one hourly streamer: tick this and pick profit share, then set their hourly rate.</span></label>
                    @if(($member_override_enabled['payout_type'] ?? false) ? (($member_override_values['payout_type'] ?? null) === 'sales_split') : (($editingMember->effectiveCompensation()['effective']['payout_type'] ?? null) === 'sales_split'))
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.house_cut_percentage">Business keeps a different %</span><input wire:model="member_override_values.house_cut_percentage" type="number" step=".01" min="0" max="100" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.sales_split_basis">Split a different figure</span><select wire:model="member_override_values.sales_split_basis" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800">@foreach(\App\Models\Streamer::salesSplitBasisLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    @endif
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.payout_percentage">Use a different Streamer Pay %</span><input wire:model="member_override_values.payout_percentage" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.hourly_rate">Use a different hourly rate</span><input wire:model="member_override_values.hourly_rate" type="number" step=".01" min="0" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"><span class="mt-1 block text-xs text-gray-500">{{ $editingMember->isRemote() ? 'This streamer is remote, so no hourly pay is added. Change them to in-house on their user profile to pay hourly.' : 'Paid for each show\'s hours on top of their share.' }}</span></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.custom_payout_formula">Use a different calculation</span><textarea wire:model="member_override_values.custom_payout_formula" rows="3" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-mono text-sm dark:bg-gray-800" placeholder="Only for this streamer"></textarea></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.include_tips">Override tip handling</span><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="member_override_values.include_tips">Include tips in pay</label></label>
                </div>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.hourly_rate">Hourly rate</span><input wire:model="member_override_values.hourly_rate" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.pwe_rate">PWE rate</span><input wire:model="member_override_values.pwe_rate" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.label_rate">Label rate</span><input wire:model="member_override_values.label_rate" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.package_rate">Flat/package rate</span><input wire:model="member_override_values.package_rate" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3 md:col-span-2"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.custom_payout_formula">Custom calculation</span><textarea wire:model="member_override_values.custom_payout_formula" rows="3" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-mono text-sm dark:bg-gray-800"></textarea></label>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap justify-end gap-3">
                @if($editingMember->status === 'inactive')
                    <button wire:click="restoreMember({{$editingMember->id}})" class="mr-auto rounded-lg border px-4 py-2 text-sm font-bold">Put back on payroll</button>
                @else
                    <button type="button" x-data x-on:click="if (await window.vxConfirm(@js('Remove '.$editingMember->name.' from payroll? A profile with no shows or pay is deleted; one with history is archived and can be restored.'))) $wire.removeMember({{$editingMember->id}})" class="mr-auto rounded-lg border border-danger-300 px-4 py-2 text-sm font-bold text-danger-700 dark:border-danger-800 dark:text-danger-300">Remove from payroll</button>
                @endif
                <button wire:click="resetMemberOverrides({{$editingMember->id}})" class="rounded-lg border px-4 py-2 text-sm font-bold">Reset to Role Default</button><button wire:click="saveMemberOverrides" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white">Save Override</button></div>
        </div>
        @endif
    </div>

    @php $schedule = $this->schedulePreview; @endphp
    <section class="vx-card p-5" aria-labelledby="pay-schedule-heading">
        <h2 id="pay-schedule-heading" class="text-lg font-bold">Pay schedule</h2>
        <p class="mt-1 text-sm text-gray-500">Which day a pay week starts on. Pay Runs already created keep their dates.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="rounded-[10px] border border-[var(--vx-border)] p-4">
                <span class="text-xs font-semibold text-gray-500">Pay week starts on</span>
                <select wire:model.live="payroll_week_start_day" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-bold dark:border-gray-600 dark:bg-gray-800">
                    @foreach(\App\Support\PaySchedule::dayOptions() as $day => $name)<option value="{{ $day }}">{{ $name }}</option>@endforeach
                </select>
            </label>
            <div class="rounded-[10px] border border-[var(--vx-border)] p-4 text-sm"><div class="text-xs font-semibold text-gray-500">Current pay period</div><div class="mt-2 font-bold">{{ $schedule['current'] }}</div></div>
            <div class="rounded-[10px] border border-[var(--vx-border)] p-4 text-sm"><div class="text-xs font-semibold text-gray-500">Next pay period</div><div class="mt-2 font-bold">{{ $schedule['next'] }}</div></div>
        </div>
        @if($schedule['changed'])
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Save to apply. The next new Pay Run starts the day after the current one ends, so no days are skipped; that one may be shorter than a week, then every run is {{ \App\Support\PaySchedule::dayOptions()[(int) $payroll_week_start_day] }} to {{ \App\Support\PaySchedule::dayOptions()[((int) $payroll_week_start_day + 6) % 7] }}.</p>
        @endif
    </section>

    <details class="vx-card p-5">
        <summary class="cursor-pointer text-lg font-bold">Pay Run Automation</summary>
        <div class="mt-4 grid gap-3 md:grid-cols-3"><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_auto_setup_enabled">Automatic weekly setup</label><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_auto_recalculate_drafts">Recalculate drafts</label><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_include_zero_activity">Include no-activity members</label></div>
    </details>

    <div class="flex justify-end"><button wire:click="save" class="rounded-[10px] bg-primary-600 px-6 py-3 text-sm font-bold text-white ">Save Payment Structures</button></div>
</div>
</x-filament-panels::page>
