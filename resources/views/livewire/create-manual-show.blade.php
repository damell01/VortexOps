<div>
    <button wire:click="openModal" type="button"
        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500">
        <x-heroicon-o-plus-circle class="h-5 w-5" />
        Create Manual Show
    </button>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeModal">
        <div class="w-full max-w-2xl rounded-xl bg-white dark:bg-gray-900 shadow-2xl max-h-screen overflow-y-auto">
            {{-- Header --}}
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-5 flex items-center justify-between sticky top-0 bg-white dark:bg-gray-900">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Create Manual Show</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Add a new show to your account</p>
                </div>
                <button wire:click="closeModal" type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- Body --}}
            <form wire:submit="createShow" class="px-6 py-6">
                {{-- Show Basics Section --}}
                <div class="space-y-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-6 bg-violet-600 rounded-full"></div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Show Details</h3>
                    </div>

                    {{-- Title --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Show Title <span class="text-red-500 font-bold">*</span>
                        </label>
                        <input type="text" wire:model="title" placeholder="e.g., Sunday Card Break"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition"
                        />
                        @error('title')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Streamers --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Streamer <span class="text-red-500 font-bold">*</span>
                        </label>
                        <select wire:model="streamerIds" multiple
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition">
                            @foreach($streamers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Select one or more streamers</p>
                        @error('streamerIds')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Channel --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Channel <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                        </label>
                        <select wire:model="whatnotChannelId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition">
                            <option value="">Select a channel...</option>
                            @foreach($channels as $channel)
                                <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                            @endforeach
                        </select>
                        @error('whatnotChannelId')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Date & Time Section --}}
                <div class="space-y-4 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-6 bg-violet-600 rounded-full"></div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Date & Time</h3>
                    </div>

                    {{-- Date & Time --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Show Date & Time <span class="text-red-500 font-bold">*</span>
                        </label>
                        <input type="datetime-local" wire:model="showDatetime"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition"
                        />
                        @error('showDatetime')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Stream Timing (Start & End Time, Duration) --}}
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Start Time <span class="text-gray-400 text-xs font-normal">(Opt.)</span>
                            </label>
                            <input type="time" wire:model.live="startTime"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition"
                            />
                            @error('startTime')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                End Time <span class="text-gray-400 text-xs font-normal">(Opt.)</span>
                            </label>
                            <input type="time" wire:model.live="endTime"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2.5 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition"
                            />
                            @error('endTime')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Duration (min) <span class="text-gray-400 text-xs font-normal">(Auto)</span>
                            </label>
                            <div class="relative">
                                <input type="number" wire:model="showDuration" placeholder="—" readonly
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-3 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none cursor-default opacity-90 transition"
                                />
                                @if($showDuration)
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-violet-600 dark:text-violet-400 font-medium">auto</span>
                                @endif
                            </div>
                            @error('showDuration')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Revenue Section --}}
                <div class="space-y-4 mb-6">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-6 bg-violet-600 rounded-full"></div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wide">Revenue</h3>
                    </div>

                    {{-- Gross Revenue --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Gross Revenue <span class="text-gray-400 text-xs font-normal">(Optional)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                            <input type="number" step="0.01" wire:model="grossRevenue" placeholder="0.00"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 pl-7 pr-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none transition"
                            />
                        </div>
                        @error('grossRevenue')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Pro Tip --}}
                <div class="rounded-lg bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/40 dark:to-indigo-950/40 border border-blue-200 dark:border-blue-800/50 p-4 mb-6">
                    <p class="text-xs leading-relaxed text-blue-900 dark:text-blue-200">
                        <span class="font-semibold">💡 Tip:</span> If the show date is in the past, you'll proceed directly to the end-of-stream form. All timing details can be filled in later.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closeModal" type="button"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50 focus:outline-none focus:ring-2 focus:ring-violet-500 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 rounded-lg bg-gradient-to-r from-violet-600 to-violet-700 px-4 py-2.5 text-sm font-semibold text-white hover:from-violet-700 hover:to-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-md"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove>Create Show</span>
                        <span wire:loading>Creating...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
