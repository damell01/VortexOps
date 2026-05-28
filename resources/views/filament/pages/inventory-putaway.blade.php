<x-filament-panels::page>
    <div class="space-y-6">
        @unless (\App\Models\InventoryContainer::schemaReady())
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 text-amber-600" />
                    <div>
                        <h2 class="text-sm font-semibold text-amber-900">Inventory workflow needs the latest migration</h2>
                        <p class="mt-1 text-sm text-amber-800">
                            The container-tracking tables are not available yet on this environment. Run the latest migrations, then this screen will move cases and boxes into their final storage locations.
                        </p>
                    </div>
                </div>
            </section>
        @endunless

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Putaway Workflow</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Move cases and boxes into their inventory locations</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Finalize the warehouse flow by moving active containers out of receiving and into main storage, streamer inventory, fulfillment, or other active locations.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Flow Step</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">3. Put away</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Moves stock too</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">Yes, automatically</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Best for</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">Cases, boxes, units</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.05fr,0.95fr]">
            <form wire:submit="moveContainer" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Move an active container</h2>
                        <p class="mt-1 text-sm text-slate-500">This updates both the container location and the item stock movement record.</p>
                    </div>
                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700">Putaway</span>
                </div>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-700">Container to move</span>
                    <select wire:model.live="container_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                        <option value="">Select case / box / unit container</option>
                        @foreach ($this->movableContainerOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('container_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Destination location</span>
                        <select wire:model="destination_location_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="">Select location</option>
                            @foreach ($this->destinationLocationOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('destination_location_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Reason / notes</span>
                        <input wire:model="reason" type="text" placeholder="Optional move note" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-slate-500">
                        @if ($this->selectedContainer())
                            Moving <span class="font-semibold text-slate-900">{{ $this->selectedContainer()->label }}</span>
                            from {{ $this->selectedContainer()->location?->name ?: 'Unknown location' }}.
                        @else
                            Select an active container to continue.
                        @endif
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-cyan-400">
                        <x-heroicon-o-arrow-right-circle class="h-5 w-5" />
                        Move container
                    </button>
                </div>
            </form>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Selected container summary</h2>
                    @if ($this->selectedContainer())
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Container</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $this->selectedContainer()->label }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Units</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ number_format((float) $this->selectedContainer()->quantity, 0) }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Item</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $this->selectedContainer()->item?->name ?? 'Unknown item' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Current location</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $this->selectedContainer()->location?->name ?? 'No location' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500">Choose a container to preview what will move.</p>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-950">Recently touched containers</h2>
                        <span class="text-xs uppercase tracking-[0.16em] text-slate-400">Latest 8</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($this->recentlyMoved() as $container)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $container->label }}</p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $container->item?->name ?? 'Unknown item' }}
                                            <span class="text-slate-400">· {{ $container->location?->name ?: 'No location' }}</span>
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">{{ number_format((float) $container->quantity, 0) }} units</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No container activity yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
