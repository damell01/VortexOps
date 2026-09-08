<x-filament-panels::page>
@php
    $pct=(float)($streamer['payout_percentage']??0);
    $ship=(float)$streamer_burden_per_shipment;
    $hour=(float)$streamer_burden_per_hour;
    $editingMember=$editing_member_id ? \App\Models\Streamer::find($editing_member_id) : null;
@endphp
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.16em] text-primary-500">Standard payroll calculation</p>
                <h2 class="mt-1 text-xl font-bold">Streamer Pay Formula</h2>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">This is the calculation from the original Streamer Log spreadsheet. It is the default for every streamer in the simulator and real weekly Pay Runs unless that person has an explicit custom override.</p>
            </div>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">Weekly · default for everyone</span>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <label class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-500">Default Streamer Pay %</span>
                <div class="mt-2 flex items-center gap-2"><input wire:model.live.debounce.300ms="streamer.payout_percentage" type="number" step=".01" class="w-full rounded-lg border-gray-300 bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"><span class="font-bold">%</span></div>
            </label>
            <label class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-500">Burden Per Shipment</span>
                <div class="mt-2 flex items-center gap-2"><span class="font-bold">$</span><input wire:model.live.debounce.300ms="streamer_burden_per_shipment" type="number" step=".01" class="w-full rounded-lg border-gray-300 bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"></div>
            </label>
            <label class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-500">Burden Per Hour</span>
                <div class="mt-2 flex items-center gap-2"><span class="font-bold">$</span><input wire:model.live.debounce.300ms="streamer_burden_per_hour" type="number" step=".01" class="w-full rounded-lg border-gray-300 bg-gray-50 text-lg font-bold dark:border-gray-600 dark:bg-gray-800"></div>
            </label>
        </div>

        <div class="mt-5 rounded-xl border border-primary-200 bg-primary-50/60 p-5 dark:border-primary-900 dark:bg-primary-950/20">
            <div class="text-xs font-bold uppercase tracking-wider text-primary-600">How payroll is calculated</div>
            <div class="mt-3 space-y-2 font-mono text-sm">
                <div>Product Cost = Σ (Inventory Cost × Quantity Sold)</div>
                <div>Burden = (Shipments × ${{number_format($ship,2)}}) + (Hours Worked × ${{number_format($hour,2)}})</div>
                <div>Net Revenue = Gross Revenue − Product Cost − Burden</div>
                <div class="font-bold text-primary-700 dark:text-primary-300">Streamer Pay = Net Revenue × {{number_format($pct,2)}}% @if($streamer['include_tips']??true) + Tips @endif</div>
            </div>
            <p class="mt-3 text-xs text-gray-500">A weekly Pay Run collects that week's eligible shows. The show calculations roll up into the person's weekly total; a custom override is only used when you intentionally set one.</p>
        </div>

        <details class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <summary class="cursor-pointer text-sm font-bold">Advanced: change the standard calculation for everyone</summary>
            <p class="mt-2 text-xs text-gray-500">Normally leave this blank. Use it only if the spreadsheet formula itself changes for the whole streamer team.</p>
            <textarea wire:model="streamer.custom_payout_formula" rows="3" class="mt-3 w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Optional team-wide custom formula"></textarea>
        </details>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold">Individual Overrides</h2>
                <p class="mt-1 text-sm text-gray-500">Filter the roster, then select a person only when their compensation should differ from the role default.</p>
            </div>
            <div class="inline-flex rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
                <button wire:click="setMemberFilter('streamer')" class="rounded-lg px-4 py-2 text-sm font-bold {{$member_filter==='streamer'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-300':'text-gray-500'}}">Streamers</button>
                <button wire:click="setMemberFilter('fulfillment')" class="rounded-lg px-4 py-2 text-sm font-bold {{$member_filter==='fulfillment'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900 dark:text-primary-300':'text-gray-500'}}">Fulfillment</button>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($this->filteredMembers as $member)
                @php $pay=$member->effectiveCompensation(); @endphp
                <button wire:click="editMember({{$member->id}})" class="rounded-xl border border-gray-200 p-4 text-left transition hover:border-primary-500 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3"><span class="font-bold">{{$member->name}}</span><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-bold uppercase text-gray-500 dark:bg-gray-800">{{$member->isFulfillment()?'Fulfillment':'Streamer'}}</span></div>
                    <div class="mt-1 text-xs text-gray-500">
                        @if(!$member->isFulfillment()){{number_format((float)($pay['effective']['payout_percentage']??0),2)}}% · @endif
                        @if(count($pay['overrides']??[])) Individual Override @else Standard {{ $member->isFulfillment() ? 'Fulfillment' : 'Streamer' }} Calculation @endif
                    </div>
                </button>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500">No {{$member_filter}} team members found.</div>
            @endforelse
        </div>

        @if($editingMember)
        <div class="mt-5 rounded-xl border-2 border-primary-300 p-5 dark:border-primary-800">
            <div class="flex items-center justify-between"><div><div class="text-xs font-bold uppercase text-primary-600">Custom override</div><div class="text-lg font-bold">{{$editingMember->name}}</div><div class="text-xs text-gray-500">Weekly payroll · {{$editingMember->isFulfillment()?'Fulfillment':'Streamer'}}</div></div><button wire:click="closeMemberEditor" class="text-sm text-gray-500">Close</button></div>

            @if(!$editingMember->isFulfillment())
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.payout_percentage">Use a different Streamer Pay %</span><input wire:model="member_override_values.payout_percentage" type="number" step=".01" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.custom_payout_formula">Use a different calculation</span><textarea wire:model="member_override_values.custom_payout_formula" rows="3" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-sm dark:bg-gray-800" placeholder="Only for this streamer"></textarea></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.include_tips">Override tip handling</span><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="member_override_values.include_tips">Include tips in pay</label></label>
                </div>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.hourly_rate">Hourly rate</span><input wire:model="member_override_values.hourly_rate" type="number" step=".01" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.pwe_rate">PWE rate</span><input wire:model="member_override_values.pwe_rate" type="number" step=".01" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.label_rate">Label rate</span><input wire:model="member_override_values.label_rate" type="number" step=".01" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.package_rate">Flat/package rate</span><input wire:model="member_override_values.package_rate" type="number" step=".01" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3 md:col-span-2"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.custom_payout_formula">Custom calculation</span><textarea wire:model="member_override_values.custom_payout_formula" rows="3" class="mt-2 w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-sm dark:bg-gray-800"></textarea></label>
                </div>
            @endif

            <div class="mt-4 flex justify-end gap-3"><button wire:click="resetMemberOverrides({{$editingMember->id}})" class="rounded-lg border px-4 py-2 text-sm font-bold">Reset to Role Default</button><button wire:click="saveMemberOverrides" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white">Save Override</button></div>
        </div>
        @endif
    </div>

    <details class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
        <summary class="cursor-pointer text-lg font-bold">Pay Run Automation</summary>
        <div class="mt-4 grid gap-3 md:grid-cols-3"><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_auto_setup_enabled">Automatic weekly setup</label><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_auto_recalculate_drafts">Recalculate drafts</label><label class="rounded-lg border p-4 text-sm font-bold"><input class="mr-2" type="checkbox" wire:model="payroll_include_zero_activity">Include no-activity members</label></div>
    </details>

    <div class="flex justify-end"><button wire:click="save" class="rounded-xl bg-primary-600 px-6 py-3 text-sm font-bold text-white shadow-lg">Save Payment Structures</button></div>
</div>
</x-filament-panels::page>
