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
<div class="space-y-6 vx-rates">
    <style>
        .vx-rates{max-width:100%;min-width:0}.vx-rates .vx-card{border-radius:18px;padding:24px}
        .vx-rates input[type=number],.vx-rates input[type=text],.vx-rates input[type=search],.vx-rates input[type=date],.vx-rates input:not([type]),.vx-rates select,.vx-rates textarea{min-height:44px;padding:10px 12px;border:1px solid var(--vx-border,#dce0e8);background:var(--vxw-surface,#fff);color:var(--vxw-text,#111827);border-radius:10px;font-size:14px;transition:border-color .15s,box-shadow .15s}
        .vx-rates input:focus,.vx-rates select:focus,.vx-rates textarea:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .13)}
        .vx-rates label input[type=checkbox]{accent-color:#7c3aed;width:16px;height:16px;flex-shrink:0}.vx-rates label{line-height:1.5}
        .vx-rates-nav{display:flex;flex-wrap:wrap;gap:8px}.vx-rates-nav a{padding:10px 16px;border:1px solid var(--vx-border,#dce0e8);border-radius:999px;background:var(--vxw-surface,#fff);font-size:13px;font-weight:650}
        .vx-rates section,.vx-rates [id]{scroll-margin-top:160px}.vx-rates .vx-type-card{border-radius:14px;background:var(--vxw-surface,#fff);box-shadow:0 2px 5px rgb(15 23 42 / .025)}
        .vx-type-title{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;font-size:15px;font-weight:700}.vx-type-create{padding:8px 12px;background:#7c3aed;color:#fff;border-radius:9px;font-size:12px;white-space:nowrap}
        .vx-rates-save{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border:1px solid var(--vx-border,#dce0e8);border-radius:14px;background:var(--vxw-surface,#fff);box-shadow:0 4px 24px rgb(15 23 42 / .12)}
        .vx-rates-save > span{font-size:13px;color:var(--vxw-muted,#64748b)}.vx-rates-save button{flex-shrink:0;background:#7c3aed!important;color:#fff!important}
        @media(max-width:640px){.vx-rates .vx-card{padding:16px}.vx-rates-save{bottom:76px;padding:12px;flex-wrap:wrap}.vx-rates-save button{width:100%}.vx-type-title{flex-wrap:wrap}}
        .vx-rates-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.vx-rates-stat{display:flex;align-items:center;gap:14px;padding:20px;border:1px solid var(--vx-border,#dce0e8);border-radius:16px;background:var(--vxw-surface,#fff)}
        .vx-rates-stat-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;width:48px;height:48px;border-radius:14px;background:rgb(124 58 237 / .1);color:#7c3aed}.vx-rates-stat-icon svg{width:25px;height:25px}.vx-rates-stat strong{font-size:24px;letter-spacing:-.02em}.vx-rates-stat p{font-size:12px;color:var(--vxw-muted,#64748b)}
        .vx-rates-stat:nth-child(2) .vx-rates-stat-icon{background:#d1fae5;color:#047857}.vx-rates-stat:nth-child(3) .vx-rates-stat-icon{background:#dbeafe;color:#2563eb}.vx-rates-stat:nth-child(4) .vx-rates-stat-icon{background:#fef3c7;color:#d97706}
        .vx-roster-filters{display:grid;grid-template-columns:minmax(200px,1fr) 220px;gap:12px;margin:18px 0}.vx-roster-table{width:100%;border-collapse:collapse;font-size:13px}.vx-roster-table th{text-align:left;padding:12px 14px;background:var(--vxw-surface-2,#f8fafc);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--vxw-muted,#64748b)}.vx-roster-table td{padding:14px;border-top:1px solid var(--vx-border,#e5e7eb)}
        .vx-roster-person{display:flex;align-items:center;gap:10px}.vx-roster-avatar{display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0;border-radius:50%;background:rgb(124 58 237 / .12);color:#6d28d9;font-weight:700}.vx-roster-badge{display:inline-block;padding:4px 9px;border-radius:999px;background:rgb(124 58 237 / .08);color:#7c3aed;font-size:11px;font-weight:650}.vx-roster-edit{padding:7px 12px;border:1px solid var(--vx-border,#ddd);border-radius:9px;font-weight:650;color:#6d28d9}.vx-roster-mobile{display:none}.vx-roster-card{padding:16px;border:1px solid var(--vx-border,#ddd);border-radius:14px;margin-top:12px}.vx-roster-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;font-size:12px;color:var(--vxw-muted,#64748b)}.vx-roster-footer button{padding:7px 12px;border:1px solid var(--vx-border,#ddd);border-radius:8px}.vx-roster-footer button:disabled{opacity:.35}
        @media(max-width:900px){.vx-rates-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-roster-table{display:none}.vx-roster-mobile{display:block}.vx-roster-filters{grid-template-columns:1fr}.vx-rates-stat{padding:14px;gap:10px}.vx-rates-stat strong{font-size:20px}.vx-rates-stat-icon{width:38px;height:38px}}
        @media(min-width:768px){.vx-rates select.vx-native-picker{display:block!important}.vx-rates .vx-picker-trigger{display:none!important}}@media(max-width:767px){.vx-rates select.vx-native-picker{display:none!important}.vx-rates .vx-picker-trigger{display:flex!important}}.vx-rates .vx-type-title button{color:#dc2626!important;background:transparent!important}
        @media(max-width:640px){.vx-rates-stat{flex-direction:column;align-items:flex-start;min-width:0}.vx-rates-stat strong{font-size:20px}.vx-rates-stat p{font-size:11px}}
    </style>
    @php $summary = $this->payrollSummary; @endphp
    <div class="vx-rates-stats">
        @foreach([['people','Team members','In the selected business','heroicon-o-user-group'],['draft_total','Draft payout total','Current draft pay runs','heroicon-o-banknotes'],['scheduled','Scheduled types','Date suggestions enabled','heroicon-o-calendar-days'],['types','Pay run types','Available for new runs','heroicon-o-squares-2x2']] as [$key,$label,$hint,$icon])
            <div class="vx-rates-stat"><span class="vx-rates-stat-icon"><x-filament::icon :icon="$icon" /></span><div><strong>{{ $key === 'draft_total' ? '$'.number_format((float)$summary[$key],2) : $summary[$key] }}</strong><p class="font-semibold">{{ $label }}</p><p>{{ $hint }}</p></div></div>
        @endforeach
    </div>
    <nav class="vx-rates-nav" aria-label="Payroll settings sections"><a href="#rates-people">People & pay methods</a><a href="#run-types-heading">Pay run types</a><a href="#rates-defaults">Team rates</a></nav>
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
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold" id="rates-people">Individual Overrides</h2>
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

        <div class="vx-roster-filters">
            <input type="search" wire:model.live.debounce.300ms="member_search" placeholder="Search people by name…" aria-label="Search payroll people" class="w-full rounded-lg border p-3">
            <select wire:model.live="pay_method_filter" aria-label="Filter pay method"><option value="">All pay methods</option><option value="profit_share">Show formula</option><option value="sales_split">Sales earnings</option><option value="hourly">Hourly wages</option><option value="pwe_labels">PWE + labels</option><option value="custom_formula">Custom formula</option></select>
        </div>
        @php $rows=$this->rosterRows; $count=$this->filteredMembers->count(); $lastPage=max(1,(int)ceil($count/10)); $page=max(1,min($member_page,$lastPage)); @endphp
        <table class="vx-roster-table"><thead><tr><th>Person</th><th>Business / channel</th><th>Pay method</th><th>Rate / structure</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($rows as $row)
                @php $member=$row['member']; @endphp
                <tr wire:key="roster-table-{{ $member->id }}"><td><div class="vx-roster-person"><span class="vx-roster-avatar">{{ $row['initials'] }}</span><div class="font-semibold">{{ $member->name }}<p class="text-xs font-normal text-gray-500">{{ $member->isFulfillment() ? 'Fulfillment' : ($member->isRemote() ? 'Remote streamer' : 'In-house streamer') }}</p></div></div></td>
                    <td>{{ $member->business?->label() ?? 'Shared rates' }}<p class="text-xs text-gray-500">{{ $member->channel?->name ?? 'No assigned channel' }}</p></td><td><span class="vx-roster-badge">{{ $row['method'] }}</span></td><td><span class="font-semibold">{{ $row['rate'] }}</span><p class="text-xs text-gray-500">{{ $row['override'] ? 'Individual override' : 'Team default' }}</p></td><td><span class="vx-roster-badge" style="color:{{ $member->status === 'inactive' ? '#64748b' : '#047857' }}">{{ $member->status === 'inactive' ? 'Archived' : 'Active' }}</span></td><td><button type="button" class="vx-roster-edit" wire:click="editMember({{ $member->id }})">Edit</button></td></tr>
            @empty <tr><td colspan="6" class="text-center text-gray-500">No people match these filters.</td></tr> @endforelse
        </tbody></table>
        <div class="vx-roster-mobile">
            @forelse($rows as $row)
                @php $member=$row['member']; @endphp
                <article class="vx-roster-card" wire:key="roster-card-{{ $member->id }}"><div class="flex items-center justify-between gap-3"><div class="vx-roster-person"><span class="vx-roster-avatar">{{ $row['initials'] }}</span><strong>{{ $member->name }}</strong></div><button type="button" class="vx-roster-edit" wire:click="editMember({{ $member->id }})">Edit</button></div><div class="mt-3 flex items-center justify-between gap-2"><span class="vx-roster-badge">{{ $row['method'] }}</span><span class="text-xs text-gray-500">{{ $member->status === 'inactive' ? 'Archived' : 'Active' }}</span></div><p class="mt-2 text-sm font-semibold">{{ $row['rate'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $member->business?->label() ?? 'Shared rates' }} · {{ $row['override'] ? 'Individual override' : 'Team default' }}</p></article>
            @empty <p class="text-sm text-gray-500">No people match these filters.</p> @endforelse
        </div>
        <div class="vx-roster-footer"><span>{{ $count ? (($page-1)*10+1).'–'.min($page*10,$count) : '0' }} of {{ $count }} people</span><div class="flex items-center gap-2"><button type="button" wire:click="setMemberPage({{ $page-1 }})" @disabled($page===1)>Previous</button><span>{{ $page }} / {{ $lastPage }}</span><button type="button" wire:click="setMemberPage({{ $page+1 }})" @disabled($page===$lastPage)>Next</button></div></div>

        @if($editingMember)
        <div class="mt-5 rounded-xl border-2 border-primary-300 p-5 dark:border-primary-800">
            <div class="flex items-center justify-between"><div><div class="text-xs font-bold uppercase text-primary-600">Custom override</div><div class="text-lg font-bold">{{$editingMember->name}}</div><div class="text-xs text-gray-500">Regular payouts · {{$editingMember->isFulfillment()?'Fulfillment':'Streamer'}}</div></div><button wire:click="closeMemberEditor" class="text-sm text-gray-500">Close</button></div>

            @if(!$editingMember->isFulfillment())
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="rounded-lg border p-3 md:col-span-2"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model.live="member_override_enabled.payout_type">Paid a different way from {{ $editingMember->business_id ? (\App\Models\Business::whereKey($editingMember->business_id)->value('name') ?? 'the team') : 'the team' }}</span>
                        <select wire:model.live="member_override_values.payout_type" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800">
                            <option value="profit_share">Regular payouts — existing show-based formula</option>
                            <option value="sales_split">Sales earnings — business keeps a %</option>
                            <option value="hourly">Hourly wages — hours worked × hourly rate</option>
                        </select>
                        <span class="mt-1 block text-xs text-gray-500">For Estill’s hourly streamer, choose Hourly wages and set their hourly rate. Profit-share pay run dates remain separate from the calculation method.</span></label>
                    @if(($member_override_enabled['payout_type'] ?? false) ? (($member_override_values['payout_type'] ?? null) === 'sales_split') : (($editingMember->effectiveCompensation()['effective']['payout_type'] ?? null) === 'sales_split'))
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.house_cut_percentage">Business keeps a different %</span><input wire:model="member_override_values.house_cut_percentage" type="number" step=".01" min="0" max="100" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.sales_split_basis">Split a different figure</span><select wire:model="member_override_values.sales_split_basis" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800">@foreach(\App\Models\Streamer::salesSplitBasisLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    @endif
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.payout_percentage">Use a different Streamer Pay %</span><input wire:model="member_override_values.payout_percentage" type="number" step=".01" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"></label>
                    <label class="rounded-lg border p-3"><span class="flex gap-2 text-sm font-bold"><input type="checkbox" wire:model="member_override_enabled.hourly_rate">Use a different hourly rate</span><input wire:model="member_override_values.hourly_rate" type="number" step=".01" min="0" class="mt-2 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 dark:bg-gray-800"><span class="mt-1 block text-xs text-gray-500">{{ (($member_override_values['payout_type'] ?? null) === 'hourly') ? 'Hours Worked × hourly rate, using the existing hourly calculation.' : ($editingMember->isRemote() ? 'Remote streamers do not receive an added hourly component with the show formula.' : 'Paid for each show\'s Hours Worked on top of their show-based payout.') }}</span></label>
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


    <section class="vx-card p-5" aria-labelledby="run-types-heading">
        <h2 id="run-types-heading" class="text-lg font-bold">Pay run types</h2>
        <p class="mt-1 text-sm text-gray-500">Create runs whenever you need them from Pay Runs → New, for any start and end dates. Wages and profit share stay separate pay lines, even in a combined run. Scheduling and automatic creation are optional for each type.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach($run_types as $index => $type)
                <div class="vx-type-card border border-[var(--vx-border)] p-4 text-sm" wire:key="run-type-{{ $index }}">
                    <div class="vx-type-title"><span>{{ $type['name'] ?: 'New pay run type' }}</span>
                        @if(($type['id'] ?? null) && ($type['active'] ?? false) && auth()->user()?->can('create', \App\Models\WeeklyPayoutBatch::class))
                            <a class="vx-type-create" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create', ['pay_run_type_id' => $type['id']]) }}">+ Create run</a>
                        @endif
                        <button type="button" class="text-xs font-semibold text-danger-600" wire:loading.attr="disabled" wire:target="deleteRunType" x-data x-on:click="if (await window.vxConfirm(@js('Delete this pay run type? Existing pay runs are preserved.'))) $wire.deleteRunType({{ $index }})">Delete</button>
                    </div>
                    <p class="mb-3 text-xs text-gray-500">{{ ($type['kind'] ?? '') === 'wages' ? 'Hourly & wages' : (($type['kind'] ?? '') === 'share' ? 'Profit share' : 'Wages + profit share') }} · {{ $type['schedule_enabled'] ? (\App\Support\PayRunSchedules::FREQUENCIES[$type['frequency']] ?? 'Scheduled dates') : 'Manual dates' }}</p>
                    <details @if(!($type['id'] ?? null)) open @endif><summary class="cursor-pointer text-xs font-semibold text-primary-600">Edit type settings</summary><div class="mt-3">
                    <label class="block"><span class="text-xs font-semibold">Type name</span>
                        <input wire:model="run_types.{{ $index }}.name" maxlength="100" placeholder="e.g. Monthly profit share" class="mt-1 w-full rounded-lg border-[var(--vx-border)] dark:bg-gray-800">
                    </label>
                    @error("run_types.$index.name")<p class="text-danger-600">{{ $message }}</p>@enderror
                    <label class="mt-3 block"><span class="text-xs font-semibold">What it pays</span>
                        <select wire:model="run_types.{{ $index }}.kind" class="mt-1 w-full rounded-lg border-[var(--vx-border)] dark:bg-gray-800">
                            <option value="wages">Hourly & wages</option><option value="share">Profit share</option><option value="">Wages + profit share</option>
                        </select>
                    </label>
                    <label class="mt-3 flex items-center gap-2"><input type="checkbox" wire:model="run_types.{{ $index }}.active"> Available for new runs</label>
                    <label class="mt-3 flex items-center gap-2"><input type="checkbox" wire:model.live="run_types.{{ $index }}.schedule_enabled"> Use a schedule to suggest dates</label>
                    @if($type['schedule_enabled'])
                        <label class="mt-3 block"><span class="text-xs font-semibold">Frequency</span>
                            <select wire:model="run_types.{{ $index }}.frequency" class="mt-1 w-full rounded-lg border-[var(--vx-border)] dark:bg-gray-800">
                                @foreach(\App\Support\PayRunSchedules::FREQUENCIES as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                            </select>
                        </label>
                        <label class="mt-3 block"><span class="text-xs font-semibold">Schedule start date (optional)</span><input type="date" wire:model="run_types.{{ $index }}.anchor" class="mt-1 w-full rounded-lg border-[var(--vx-border)] dark:bg-gray-800"></label>
                        @error("run_types.$index.anchor")<p class="text-danger-600">{{ $message }}</p>@enderror
                        <label class="mt-3 flex items-center gap-2"><input type="checkbox" wire:model="run_types.{{ $index }}.auto_create"> Automatically create draft runs for this type</label>
                        <p class="mt-2 text-xs text-gray-500">Leave unchecked to create runs yourself. Any scheduled dates can be overridden when creating a run. Nothing is finalized or paid automatically.</p>
                    @else
                        <p class="mt-3 text-xs text-gray-500">Manual creation · Choose dates each time.</p>
                    @endif
                    </div></details>
                </div>
            @endforeach
        </div>
        <button type="button" wire:click="addRunType" class="mt-4 rounded-lg border border-[var(--vx-border)] px-4 py-2 text-sm font-semibold">Add pay run type</button>
    </section>

    <div class="vx-card p-5">
        <p id="rates-defaults" class="text-xs font-bold uppercase tracking-[.16em] text-primary-500">Regular payouts{{ $business ? ' · '.$business->name : '' }}</p>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <label class="flex cursor-pointer gap-3 rounded-[10px] border p-4 {{ ! $isSplit ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-[var(--vx-border)]' }}">
                <input type="radio" wire:model.live="streamer.payout_type" value="profit_share" class="mt-1">
                <span><span class="block font-bold">Regular payouts</span><span class="block text-xs text-gray-500">The existing show-based formula after product cost and burden, with hourly pay where configured. Choose pay run dates separately.</span></span>
            </label>
            <label class="flex cursor-pointer gap-3 rounded-[10px] border p-4 {{ $isSplit ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-[var(--vx-border)]' }}">
                <input type="radio" wire:model.live="streamer.payout_type" value="sales_split" class="mt-1">
                <span><span class="block font-bold">Sales split</span><span class="block text-xs text-gray-500">The business keeps a % of what the streamer's shows earned; the streamer gets the rest. Set this per person or as the business default.</span></span>
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
                <p class="mt-1 max-w-3xl text-sm text-gray-500">This is the calculation from the original Streamer Log spreadsheet. It is the default for every streamer in the simulator and real Pay Runs unless that person has an explicit custom override.</p>
            </div>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">Existing formula · team default</span>
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
            <p class="mt-3 text-xs text-gray-500">A Pay Run collects eligible shows for its chosen dates, including a monthly profit-share run. Each show still uses the supplied calculation; a custom override is only used when you intentionally set one.</p>
        </div>

        <details class="mt-5 rounded-[10px] border border-[var(--vx-border)] p-4">
            <summary class="cursor-pointer text-sm font-bold">Advanced: change the standard calculation for everyone</summary>
            <p class="mt-2 text-xs text-gray-500">Normally leave this blank. Use it only if the spreadsheet formula itself changes for the whole streamer team.</p>
            <textarea wire:model="streamer.custom_payout_formula" rows="3" class="mt-3 w-full rounded-[10px] border-[var(--vx-border)] bg-gray-50 font-mono text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Optional team-wide custom formula"></textarea>
        </details>
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
        <summary class="cursor-pointer text-lg font-bold">Draft calculation options</summary>
        <div class="mt-4 grid gap-3 md:grid-cols-2"><label class="rounded-lg border p-4 text-sm"><input class="mr-2" type="checkbox" wire:model="payroll_auto_recalculate_drafts">Recalculate drafts during opted-in scheduled setup</label><label class="rounded-lg border p-4 text-sm"><input class="mr-2" type="checkbox" wire:model="payroll_include_zero_activity">Include no-activity members</label></div>
    </details>

    <div class="vx-rates-save"><span>Save rate and type changes before creating a pay run.</span><button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-[10px] bg-primary-600 px-6 py-3 text-sm font-bold text-white"><span wire:loading.remove wire:target="save">Save changes</span><span wire:loading wire:target="save">Saving…</span></button></div>
</div>
</x-filament-panels::page>
