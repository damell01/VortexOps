<x-filament-panels::page>
    @php($summary = $this->breakdownSummary())

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Container Workflow</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Break down pallets into cases or smaller units</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Use this when inbound pallets need to be split into cases, boxes, or unit containers before putaway. Parent quantity is reduced automatically as child containers are created.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Parent qty planned</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format((float) $summary['planned'], 0) }} units</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Remainder</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format((float) $summary['remainder'], 0) }} units</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Containers created</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $summary['will_create'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
            <form wire:submit="runBreakdown" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Split a parent container</h2>
                        <p class="mt-1 text-sm text-slate-500">Create child case records without manually rebuilding the inventory structure.</p>
                    </div>
                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700">Breakdown</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 md:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Parent container</span>
                        <select wire:model.live="parent_container_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="">Select parent pallet / case</option>
                            @foreach ($this->parentContainerOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('parent_container_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Child type</span>
                        <select wire:model="child_type" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="case">Case</option>
                            <option value="box">Box</option>
                            <option value="unit">Unit</option>
                            <option value="other">Other</option>
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Destination location</span>
                        <select wire:model="destination_location_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="">Select location</option>
                            @foreach ($this->locationOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('destination_location_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">How many child containers?</span>
                        <input wire:model="child_count" type="number" min="1" step="1" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                        @error('child_count') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Units per child</span>
                        <input wire:model="units_per_child" type="number" min="0.01" step="0.01" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                        @error('units_per_child') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2 md:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Label prefix</span>
                        <input wire:model="label_prefix" type="text" placeholder="Example: PALLET-001-CASE" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                </div>

                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <input wire:model="create_remainder_case" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                    Automatically create one extra child container for any remaining units
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-700">Notes for created child containers</span>
                    <textarea wire:model="notes" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm"></textarea>
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-slate-500">
                        @if ($this->selectedParent())
                            Splitting <span class="font-semibold text-slate-900">{{ $this->selectedParent()->label }}</span>
                            with {{ number_format((float) $this->selectedParent()->quantity, 0) }} available units.
                        @else
                            Choose a pallet or case to see the current available quantity.
                        @endif
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-cyan-400">
                        <x-heroicon-o-scissors class="h-5 w-5" />
                        Run breakdown
                    </button>
                </div>
            </form>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Selected parent summary</h2>
                    @if ($this->selectedParent())
                        <div class="mt-4 space-y-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Container</p>
                                <p class="mt-1 text-lg font-semibold text-slate-950">{{ $this->selectedParent()->label }}</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Item</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ $this->selectedParent()->item?->name ?? 'Unknown item' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Current location</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ $this->selectedParent()->location?->name ?? 'No location' }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Available units</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ number_format((float) $this->selectedParent()->quantity, 0) }}</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">After this split</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ number_format((float) $summary['remainder'], 0) }} units remain</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">Choose a parent container to preview the breakdown plan.</p>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-950">Recent child containers</h2>
                        <span class="text-xs uppercase tracking-[0.16em] text-slate-400">Latest 8</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($this->recentlyBrokenDown() as $container)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $container->label }}</p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            From {{ $container->parentContainer?->label ?? 'Parent container' }}
                                            <span class="text-slate-400">· {{ $container->location?->name ?: 'No location' }}</span>
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">{{ number_format((float) $container->quantity, 0) }} units</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No child containers created yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
