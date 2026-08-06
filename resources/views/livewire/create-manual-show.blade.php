<div>
    <button wire:click="openModal" type="button"
        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500">
        <x-heroicon-o-plus-circle class="h-5 w-5" />
        Create Manual Show
    </button>

    {{-- Modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click.self="closeModal">
        <div class="w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 shadow-lg max-h-screen overflow-y-auto">
            {{-- Header --}}
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between sticky top-0 bg-white dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Create Manual Show</h2>
                <button wire:click="closeModal" type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- Body --}}
            <form wire:submit="createShow" class="space-y-4 px-6 py-4">
                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Show Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="title" placeholder="e.g., Sunday Card Break"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                    />
                    @error('title')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Streamers --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Streamer <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="streamerIds" multiple
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none">
                        @foreach($streamers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select one or more streamers</p>
                    @error('streamerIds')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Channel --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Channel (Optional)
                    </label>
                    <select wire:model="whatnotChannelId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none">
                        <option value="">Select a channel...</option>
                        @foreach($channels as $channel)
                            <option value="{{ $channel->id }}">{{ $channel->name }}</option>
                        @endforeach
                    </select>
                    @error('whatnotChannelId')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Date & Time --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Date & Time <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" wire:model="showDatetime"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                    />
                    @error('showDatetime')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Stream Timing (Start & End Time, Duration) --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Time (Optional)
                        </label>
                        <input type="time" wire:model="startTime"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                        />
                        @error('startTime')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            End Time (Optional)
                        </label>
                        <input type="time" wire:model="endTime"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                        />
                        @error('endTime')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Duration (minutes) (Optional)
                        </label>
                        <input type="number" wire:model="showDuration" placeholder="e.g., 60"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                        />
                        @error('showDuration')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Gross Revenue --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Gross Revenue (Optional)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-400">$</span>
                        <input type="number" step="0.01" wire:model="grossRevenue" placeholder="0.00"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 pl-6 pr-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                        />
                    </div>
                    @error('grossRevenue')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Pro Tip --}}
                <div class="rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 p-3">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        💡 <strong>Pro tip:</strong> If the show is in the past, you'll go straight to the end-of-stream log form. You can fill in timing details later.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-4">
                    <button wire:click="closeModal" type="button"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed"
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
