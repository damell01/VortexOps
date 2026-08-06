<div>
    <!-- Create Show Button -->
    <button
        wire:click="openModal"
        class="mb-6 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition flex items-center gap-2 touch-manipulation"
    >
        <span class="text-xl">➕</span>
        <span>Create Manual Show</span>
    </button>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-end md:items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-t-lg md:rounded-lg w-full md:max-w-md shadow-xl max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Create Manual Show</h2>
                    <button
                        wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl leading-none"
                    >
                        ✕
                    </button>
                </div>

                <!-- Content -->
                <form wire:submit="createShow" class="p-6 space-y-5">
                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Show Title
                        </label>
                        <input
                            type="text"
                            wire:model="title"
                            placeholder="e.g., Football Card Break #42"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-300 focus:outline-none transition"
                        />
                        @error('title')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Channel -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Channel / Platform
                        </label>
                        <input
                            type="text"
                            wire:model="channel"
                            placeholder="e.g., Whatnot, YouTube, TikTok"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-300 focus:outline-none transition"
                        />
                        @error('channel')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Show Date & Time -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Date & Time
                        </label>
                        <input
                            type="datetime-local"
                            wire:model="showDatetime"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-300 focus:outline-none transition"
                        />
                        @error('showDatetime')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            If the show is in the past, you'll go straight to the end-of-stream log form
                        </p>
                    </div>

                    <!-- Gross Revenue -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            Gross Revenue
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">$</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model="grossRevenue"
                                placeholder="0.00"
                                class="w-full pl-8 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-300 focus:outline-none transition"
                            />
                        </div>
                        @error('grossRevenue')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="submit"
                            class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition touch-manipulation"
                        >
                            Create Show
                        </button>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="flex-1 px-4 py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-white rounded-lg font-semibold transition touch-manipulation"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
