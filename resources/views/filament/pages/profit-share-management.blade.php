<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Profit Share Distribution Form --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Split Profit Share</h2>

            <form wire:submit="submitDistribution" class="space-y-6">
                {{-- Select Payout --}}
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Select Show Payout
                    </label>
                    <select wire:model.live="selectedShowId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:focus:border-primary-400 dark:focus:ring-primary-400/20">
                        <option value="">-- Select a payout --</option>
                        @foreach ($this->approvedPayouts as $payout)
                            <option value="{{ $payout->id }}">
                                {{ $payout->show?->title }} ({{ $payout->show?->show_date?->format('M j, Y') }})
                                — Profit Share: ${{ number_format($payout->calculated_payout, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($this->selectedShowId)
                    @php
                        $selectedPayout = $this->approvedPayouts->firstWhere('id', $this->selectedShowId);
                        if ($selectedPayout && !$this->profitShareAmount) {
                            $this->profitShareAmount = $selectedPayout->calculated_payout;
                        }
                    @endphp

                    {{-- Distribution Preview --}}
                    <div class="rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6 border border-blue-200 dark:border-blue-800">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">Distribution Preview</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {{-- Total Amount --}}
                            <div class="rounded-lg bg-white dark:bg-gray-800 p-4 border border-gray-200 dark:border-gray-700">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                                    Total Profit Share
                                </div>
                                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    ${{ number_format($this->profitShareAmount, 2) }}
                                </div>
                            </div>

                            {{-- Streamer Share --}}
                            <div class="rounded-lg bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 p-4 border border-green-200 dark:border-green-800">
                                <div class="text-xs font-semibold text-green-900 dark:text-green-100 uppercase tracking-wide">
                                    Streamer Gets
                                </div>
                                <div class="mt-2 text-2xl font-bold text-green-700 dark:text-green-400">
                                    ${{ number_format($this->profitShareAmount * (1 - ($this->managerPercentage / 100)), 2) }}
                                </div>
                                <div class="text-xs text-green-700 dark:text-green-300 mt-1">
                                    {{ 100 - $this->managerPercentage }}%
                                </div>
                            </div>

                            {{-- Manager Share --}}
                            <div class="rounded-lg bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 p-4 border border-purple-200 dark:border-purple-800">
                                <div class="text-xs font-semibold text-purple-900 dark:text-purple-100 uppercase tracking-wide">
                                    Manager Gets
                                </div>
                                <div class="mt-2 text-2xl font-bold text-purple-700 dark:text-purple-400">
                                    ${{ number_format($this->profitShareAmount * ($this->managerPercentage / 100), 2) }}
                                </div>
                                <div class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                                    {{ $this->managerPercentage }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Distribution Controls --}}
                    <div class="space-y-4">
                        {{-- Profit Share Amount --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                Profit Share Amount
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-600 dark:text-gray-400">$</span>
                                <input type="number"
                                       step="0.01"
                                       wire:model.live="profitShareAmount"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 pl-8 pr-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" />
                            </div>
                        </div>

                        {{-- Manager Percentage --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                Manager Share Percentage
                            </label>
                            <div class="space-y-3">
                                <input type="range"
                                       min="0"
                                       max="100"
                                       step="5"
                                       wire:model.live="managerPercentage"
                                       class="w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary-600" />
                                <div class="flex items-center gap-2">
                                    <input type="number"
                                           min="0"
                                           max="100"
                                           wire:model.live="managerPercentage"
                                           class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" />
                                    <span class="text-sm text-gray-600 dark:text-gray-400">%</span>
                                </div>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                Distribution Notes (optional)
                            </label>
                            <textarea wire:model="managerNotes"
                                      rows="3"
                                      placeholder="Add any notes about this profit share arrangement..."
                                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"></textarea>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit"
                            class="w-full rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-500 transition-colors flex items-center justify-center gap-2">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        Save Distribution
                    </button>
                @endif
            </form>
        </div>

        {{-- Pending Distributions --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Pending Distributions</h2>

            <div class="space-y-3">
                @forelse ($this->approvedPayouts->filter(fn ($p) => $p->manager_share_percentage) as $payout)
                    <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $payout->show?->title }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Profit Share: ${{ number_format($payout->calculated_payout, 2) }}
                                    — Streamer: ${{ number_format($payout->streamer_share_amount, 2) }}
                                    — Manager: ${{ number_format($payout->manager_share_amount, 2) }}
                                </div>
                                @if ($payout->distribution_notes)
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-2 italic">
                                        "{{ $payout->distribution_notes }}"
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-semibold text-yellow-900 dark:text-yellow-100 uppercase tracking-wide">
                                    Pending Approval
                                </div>
                                <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-400 mt-1">
                                    {{ $payout->manager_share_percentage }}%
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-inbox class="h-12 w-12 mx-auto mb-3 opacity-50" />
                        <p>No pending distributions</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
