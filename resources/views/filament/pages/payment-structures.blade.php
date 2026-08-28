<x-filament-panels::page>
    @php
        $types = \App\Models\Streamer::payoutTypeLabels();
        $stats = $this->structureStats;
    @endphp

    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-2">
            @foreach(['streamer' => 'Streamer', 'fulfillment' => 'Fulfillment'] as $key => $label)
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">Default Payment Structure</p>
                            <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $label }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Inherited by opted-in {{ strtolower($label) }} team members unless a specific field is overridden.</p>
                        </div>
                        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                            <div>{{ $stats[$key]['total'] }} members</div>
                            <div>{{ $stats[$key]['inheriting'] }} inheriting · {{ $stats[$key]['custom'] }} custom</div>
                            @if($stats[$key]['legacy'] > 0)<div class="mt-1 text-amber-600 dark:text-amber-400">{{ $stats[$key]['legacy'] }} legacy-safe</div>@endif
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1.5 block text-sm font-medium">Payout Type</label><select wire:model="{{ $key }}.payout_type" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">@foreach($types as $value => $name)<option value="{{ $value }}">{{ $name }}</option>@endforeach</select></div>
                        <div><label class="mb-1.5 block text-sm font-medium">Pay Run Cadence</label><select wire:model="{{ $key }}.payout_cadence" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
                        <div><label class="mb-1.5 block text-sm font-medium">Profit Share / Payout %</label><input wire:model="{{ $key }}.payout_percentage" type="number" step="0.01" min="0" max="100" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                        <div><label class="mb-1.5 block text-sm font-medium">Hourly Rate</label><input wire:model="{{ $key }}.hourly_rate" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                        <div><label class="mb-1.5 block text-sm font-medium">PWE Rate</label><input wire:model="{{ $key }}.pwe_rate" type="number" step="0.0001" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                        <div><label class="mb-1.5 block text-sm font-medium">Label Rate</label><input wire:model="{{ $key }}.label_rate" type="number" step="0.0001" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                        <div><label class="mb-1.5 block text-sm font-medium">Package / Flat Rate</label><input wire:model="{{ $key }}.package_rate" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                        <label class="flex items-center gap-3 self-end rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700"><input type="checkbox" wire:model="{{ $key }}.include_tips" class="rounded border-gray-300 text-primary-600"><span class="text-sm font-medium">Include tips</span></label>
                    </div>
                    <div class="mt-4"><label class="mb-1.5 block text-sm font-medium">Custom Formula</label><textarea wire:model="{{ $key }}.custom_payout_formula" rows="2" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Only used when payout type is Custom Formula"></textarea></div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-bold">Team Member Overrides</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defaults are managed once above. Customize only the fields that are different for a specific person.</p>
            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3">Member</th><th class="px-4 py-3">Team</th><th class="px-4 py-3">Structure Status</th><th class="px-4 py-3">Effective Pay</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->members as $member)
                        @php $pay = $member->effectiveCompensation(); $custom = !$pay['legacy'] && count($pay['overrides']) > 0; @endphp
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $member->name }}</td>
                            <td class="px-4 py-3">{{ $member->isFulfillment() ? 'Fulfillment' : 'Streamer' }}</td>
                            <td class="px-4 py-3">
                                @if($pay['legacy'])<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Legacy-safe</span>
                                @elseif($custom)<span class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800">Custom override</span>
                                @else<span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Using default</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $types[$pay['effective']['payout_type'] ?? ''] ?? ($pay['effective']['payout_type'] ?? '—') }}
                                @if(($pay['effective']['payout_percentage'] ?? null) !== null) · {{ number_format((float)$pay['effective']['payout_percentage'], 2) }}%@endif
                                @if(($pay['effective']['hourly_rate'] ?? null) !== null) · ${{ number_format((float)$pay['effective']['hourly_rate'], 2) }}/hr @endif
                                @if(($pay['effective']['label_rate'] ?? null) !== null) · ${{ number_format((float)$pay['effective']['label_rate'], 4) }}/label @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($pay['legacy'])<button wire:click="adoptDefaults({{ $member->id }})" wire:confirm="Move {{ $member->name }} onto the current {{ $member->isFulfillment() ? 'Fulfillment' : 'Streamer' }} defaults? Their current legacy values will be replaced by the team defaults." class="text-sm font-semibold text-amber-600">Use Team Default</button>
                                @else<button wire:click="editMember({{ $member->id }})" class="text-sm font-semibold text-primary-600">Customize</button>@if($custom)<button wire:click="resetMemberOverrides({{ $member->id }})" wire:confirm="Remove all individual overrides for {{ $member->name }}?" class="ml-3 text-sm text-gray-500">Reset</button>@endif @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($editing_member_id)
            @php $editingMember = \App\Models\Streamer::find($editing_member_id); @endphp
            <div class="rounded-xl border-2 border-primary-200 bg-white p-6 dark:border-primary-800 dark:bg-gray-900">
                <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase text-primary-600">Individual Compensation</p><h2 class="text-lg font-bold">{{ $editingMember?->name }}</h2></div><button wire:click="closeMemberEditor" class="text-sm text-gray-500">Close</button></div>
                <p class="mt-2 text-sm text-gray-500">Check a field only when this person should differ from the team default. Unchecked fields continue inheriting future default changes.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach(['payout_percentage'=>'Profit Share %','hourly_rate'=>'Hourly Rate','pwe_rate'=>'PWE Rate','label_rate'=>'Label Rate','package_rate'=>'Package / Flat Rate'] as $field=>$label)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="member_override_enabled.{{ $field }}" class="rounded">Override {{ $label }}</label><input wire:model="member_override_values.{{ $field }}" type="number" step="0.01" class="mt-2 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></div>
                    @endforeach
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="member_override_enabled.payout_type" class="rounded">Override Payout Type</label><select wire:model="member_override_values.payout_type" class="mt-2 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">@foreach($types as $value=>$name)<option value="{{ $value }}">{{ $name }}</option>@endforeach</select></div>
                </div>
                <div class="mt-5 flex justify-end gap-3"><button wire:click="closeMemberEditor" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Cancel</button><button wire:click="saveMemberOverrides" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Save Overrides</button></div>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-bold">Pay Run Automation</h2>
            <p class="mt-1 text-sm text-gray-500">Draft weekly Pay Runs can be set up and refreshed by the scheduler. Finalized, submitted and paid payroll is never auto-recalculated.</p>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="flex items-start gap-3 rounded-lg border p-4"><input type="checkbox" wire:model="payroll_auto_setup_enabled" class="mt-0.5 rounded"><div><div class="text-sm font-semibold">Automatic Pay Run Setup</div><div class="mt-1 text-xs text-gray-500">Ensures the current Monday–Sunday Draft Pay Run exists.</div></div></label>
                <label class="flex items-start gap-3 rounded-lg border p-4"><input type="checkbox" wire:model="payroll_auto_recalculate_drafts" class="mt-0.5 rounded"><div><div class="text-sm font-semibold">Recalculate Drafts</div><div class="mt-1 text-xs text-gray-500">Refreshes show contributions as reports and activity are completed.</div></div></label>
                <label class="flex items-start gap-3 rounded-lg border p-4"><input type="checkbox" wire:model="payroll_include_zero_activity" class="mt-0.5 rounded"><div><div class="text-sm font-semibold">Include No-Activity Members</div><div class="mt-1 text-xs text-gray-500">Reserved for payrolls that require a zero-activity line.</div></div></label>
            </div>
            <div class="mt-4 text-xs text-gray-500">Last successful automation: <strong>{{ \App\Models\Setting::get('payroll_last_automation_success_at', 'Never') }}</strong>@if(\App\Models\Setting::get('payroll_last_automation_error'))<span class="ml-3 text-red-600">Last error: {{ \App\Models\Setting::get('payroll_last_automation_error') }}</span>@endif</div>
        </div>

        <div class="flex justify-end"><button type="button" wire:click="save" class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white">Save Payment Structures</button></div>
    </div>
</x-filament-panels::page>
