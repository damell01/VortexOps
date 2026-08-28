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
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Team members inherit these values unless a field is overridden on their profile.</p>
                        </div>
                        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                            <div>{{ $stats[$key]['total'] }} members</div>
                            <div>{{ $stats[$key]['inheriting'] }} inheriting · {{ $stats[$key]['custom'] }} custom</div>
                            @if($stats[$key]['legacy'] > 0)
                                <div class="mt-1 text-amber-600 dark:text-amber-400">{{ $stats[$key]['legacy'] }} legacy-safe</div>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Payout Type</label>
                            <select wire:model="{{ $key }}.payout_type" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                @foreach($types as $value => $name)
                                    <option value="{{ $value }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Pay Run Cadence</label>
                            <select wire:model="{{ $key }}.payout_cadence" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Profit Share / Payout %</label>
                            <div class="relative"><input wire:model="{{ $key }}.payout_percentage" type="number" step="0.01" min="0" max="100" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 pr-8 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"><span class="absolute right-3 top-2 text-sm text-gray-400">%</span></div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Hourly Rate</label>
                            <div class="relative"><span class="absolute left-3 top-2 text-sm text-gray-400">$</span><input wire:model="{{ $key }}.hourly_rate" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-7 pr-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">PWE Rate</label>
                            <div class="relative"><span class="absolute left-3 top-2 text-sm text-gray-400">$</span><input wire:model="{{ $key }}.pwe_rate" type="number" step="0.0001" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-7 pr-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Label Rate</label>
                            <div class="relative"><span class="absolute left-3 top-2 text-sm text-gray-400">$</span><input wire:model="{{ $key }}.label_rate" type="number" step="0.0001" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-7 pr-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Package / Flat Rate</label>
                            <div class="relative"><span class="absolute left-3 top-2 text-sm text-gray-400">$</span><input wire:model="{{ $key }}.package_rate" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-7 pr-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"></div>
                        </div>
                        <label class="flex items-center gap-3 self-end rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                            <input type="checkbox" wire:model="{{ $key }}.include_tips" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Include tips</span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Custom Formula</label>
                        <textarea wire:model="{{ $key }}.custom_payout_formula" rows="2" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Only used when payout type is Custom Formula"></textarea>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Pay Run Automation</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Draft weekly Pay Runs can be set up and refreshed by the scheduler. Finalized, submitted and paid payroll is never auto-recalculated.</p>

            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <input type="checkbox" wire:model="payroll_auto_setup_enabled" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <div><div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Automatic Pay Run Setup</div><div class="mt-1 text-xs text-gray-500">Ensures the current Monday–Sunday Draft Pay Run exists.</div></div>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <input type="checkbox" wire:model="payroll_auto_recalculate_drafts" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <div><div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recalculate Drafts</div><div class="mt-1 text-xs text-gray-500">Refreshes show contributions as reports and activity are completed.</div></div>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <input type="checkbox" wire:model="payroll_include_zero_activity" class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <div><div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Include No-Activity Members</div><div class="mt-1 text-xs text-gray-500">Reserved for payrolls that require a zero-activity line for active team members.</div></div>
                </label>
            </div>

            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                Last successful automation: <strong>{{ \App\Models\Setting::get('payroll_last_automation_success_at', 'Never') }}</strong>
                @if(\App\Models\Setting::get('payroll_last_automation_error'))
                    <span class="ml-3 text-red-600 dark:text-red-400">Last error: {{ \App\Models\Setting::get('payroll_last_automation_error') }}</span>
                @endif
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="save" class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Save Payment Structures</button>
        </div>
    </div>
</x-filament-panels::page>
