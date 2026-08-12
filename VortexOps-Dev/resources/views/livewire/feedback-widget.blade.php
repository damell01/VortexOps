<div id="feedback-widget-root"
     x-data="{ open: false }"
     @keydown.escape.window="open = false">

    {{-- Floating trigger button --}}
    <button
        @click="open = true"
        x-show="!open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        title="Leave feedback"
        class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-gray-900 dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white shadow-lg ring-1 ring-white/10 hover:bg-gray-800 dark:hover:bg-gray-700 active:scale-95 transition-all duration-150">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
        </svg>
        Feedback
    </button>

    {{-- Backdrop + modal --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        @click.self="open = false">

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden flex flex-col"
            style="max-height: 90vh;">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Leave Feedback</h2>
                </div>
                <button @click="open = false" class="rounded-lg p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            @if ($submitted)
                {{-- Success state --}}
                <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Feedback Submitted!</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Thanks — the team has been notified and will review your feedback.</p>
                    <button
                        wire:click="resetWidget"
                        @click="open = false"
                        class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                        Done
                    </button>
                </div>
            @else
                {{-- Form --}}
                <div class="overflow-y-auto">
                    <div class="p-5 space-y-4">

                        {{-- Title --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                What's the issue? <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.blur="title"
                                type="text"
                                placeholder="e.g. Button doesn't save, wrong total shown…"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                            @error('title')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                More details <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <textarea
                                wire:model.blur="description"
                                rows="3"
                                placeholder="Steps to reproduce, expected vs actual behavior…"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none resize-none transition-colors">
                            </textarea>
                        </div>

                        {{-- Priority --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority</label>
                            <div class="flex gap-4">
                                @foreach(['low' => ['Low', 'text-blue-500'], 'medium' => ['Medium', 'text-amber-500'], 'high' => ['High', 'text-red-500']] as $val => [$label, $color])
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input wire:model="priority" type="radio" value="{{ $val }}"
                                               class="text-primary-600 focus:ring-primary-500">
                                        <span class="text-sm {{ $color }} font-medium">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Screenshot upload --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Screenshot <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 px-4 py-3 text-xs text-gray-500 dark:text-gray-400 mb-2 space-y-1">
                                <p class="font-medium text-gray-600 dark:text-gray-300">How to take a screenshot:</p>
                                <p><span class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">Win + Shift + S</span> — Windows (Snipping Tool, saves to clipboard)</p>
                                <p><span class="font-mono bg-gray-100 dark:bg-gray-700 px-1 rounded">Cmd + Shift + 4</span> — Mac (saves to Desktop)</p>
                                <p class="text-gray-400 dark:text-gray-500">Then upload the file below.</p>
                            </div>
                            <input
                                wire:model="screenshot"
                                type="file"
                                accept="image/*"
                                class="block w-full text-sm text-gray-500 dark:text-gray-400
                                       file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0
                                       file:text-xs file:font-medium file:bg-primary-50 dark:file:bg-primary-900/30
                                       file:text-primary-700 dark:file:text-primary-400 hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50
                                       cursor-pointer">
                            @error('screenshot')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                            @if ($screenshot)
                                <div class="mt-2 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 max-h-40">
                                    <img src="{{ $screenshot->temporaryUrl() }}" class="w-full object-contain" alt="Screenshot preview">
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                        <button @click="open = false" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-50 flex items-center gap-2 transition-colors">
                            <span wire:loading wire:target="submit" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Submit Feedback
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
