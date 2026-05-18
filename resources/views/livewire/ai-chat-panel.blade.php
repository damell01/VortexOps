<div
    x-data="{
        open: @entangle('isOpen').live,
    }"
    x-on:panel-scroll-to-bottom.window="$nextTick(() => {
        const el = document.getElementById('ai-panel-messages');
        if (el) el.scrollTop = el.scrollHeight;
    })"
    class="fixed bottom-6 right-6 z-[99999] flex flex-col items-end gap-2"
>
    {{-- ── Floating panel ───────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="w-96 rounded-2xl shadow-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden"
        style="height: 520px"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-2.5 bg-violet-600 text-white shrink-0">
            <div class="flex items-center gap-2">
                <x-heroicon-o-sparkles class="h-4 w-4" />
                <span class="text-sm font-semibold">AI Assistant</span>
                @if ($ollamaOnline)
                    <span class="flex h-1.5 w-1.5 rounded-full bg-green-400"></span>
                @else
                    <span class="flex h-1.5 w-1.5 rounded-full bg-red-400"></span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="clearChat" title="Clear chat"
                    class="opacity-60 hover:opacity-100 transition">
                    <x-heroicon-o-trash class="h-3.5 w-3.5" />
                </button>
                <button wire:click="refreshContext" title="Refresh context"
                    class="opacity-60 hover:opacity-100 transition">
                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                </button>
                <button @click="open = false" class="opacity-60 hover:opacity-100 transition">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Context badge --}}
        @if ($contextLabel && $contextLabel !== 'VortexOps')
            <div class="px-4 py-1.5 bg-violet-50 dark:bg-violet-950 border-b border-violet-100 dark:border-violet-900 shrink-0 flex items-center gap-1.5">
                <x-heroicon-o-eye class="h-3 w-3 text-violet-500" />
                <span class="text-xs text-violet-700 dark:text-violet-300 truncate">Context: <strong>{{ $contextLabel }}</strong></span>
            </div>
        @endif

        {{-- Messages --}}
        <div id="ai-panel-messages" class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50 dark:bg-gray-950">
            @forelse ($messages as $msg)
                @if ($msg['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-br-sm bg-violet-600 text-white px-3 py-2 text-xs shadow-sm">
                                {{ $msg['content'] }}
                            </div>
                            <div class="text-right text-[10px] text-gray-400 mt-0.5">{{ $msg['time'] }}</div>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start gap-2">
                        <div class="mt-0.5 shrink-0 rounded-full bg-violet-100 dark:bg-violet-900 p-1">
                            <x-heroicon-o-sparkles class="h-3 w-3 text-violet-500" />
                        </div>
                        <div class="max-w-[85%]">
                            <div class="rounded-2xl rounded-bl-sm px-3 py-2 text-xs shadow-sm border
                                {{ isset($msg['success']) && !$msg['success']
                                    ? 'bg-red-50 dark:bg-red-950 border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'
                                    : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200' }}">
                                <div class="whitespace-pre-wrap">{{ $msg['content'] }}</div>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                {{ $msg['time'] }}
                                @if (!empty($msg['latency']))
                                    · {{ number_format($msg['latency'] / 1000, 1) }}s
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex flex-col items-center justify-center h-full gap-2 py-8 text-center">
                    <x-heroicon-o-sparkles class="h-8 w-8 text-violet-300" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Ask anything about your inventory</p>
                    @if ($contextLabel && $contextLabel !== 'VortexOps')
                        <p class="text-[10px] text-gray-400">I can see you're viewing <strong>{{ $contextLabel }}</strong></p>
                    @endif
                </div>
            @endforelse

            @if ($isLoading)
                <div class="flex justify-start gap-2">
                    <div class="mt-0.5 shrink-0 rounded-full bg-violet-100 dark:bg-violet-900 p-1">
                        <x-heroicon-o-sparkles class="h-3 w-3 text-violet-500" />
                    </div>
                    <div class="rounded-2xl rounded-bl-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2.5 shadow-sm">
                        <div class="flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:0ms"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:150ms"></span>
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:300ms"></span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <div class="px-3 py-2.5 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shrink-0">
            <form wire:submit="sendMessage" class="flex gap-1.5">
                <input
                    wire:model="question"
                    type="text"
                    placeholder="{{ $ollamaOnline ? 'Ask anything…' : 'Ollama offline' }}"
                    :disabled="{{ $isLoading ? 'true' : 'false' }}"
                    {{ ! $ollamaOnline ? 'disabled' : '' }}
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none disabled:opacity-50"
                />
                <button
                    type="submit"
                    :disabled="{{ $isLoading || !$ollamaOnline ? 'true' : 'false' }}"
                    {{ $isLoading || ! $ollamaOnline ? 'disabled' : '' }}
                    class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-white hover:bg-violet-700 disabled:opacity-50 transition"
                >
                    <x-heroicon-o-paper-airplane class="h-3.5 w-3.5" />
                </button>
            </form>
        </div>
    </div>

    {{-- ── Toggle button ────────────────────────────────────────────── --}}
    <button
        @click="open = !open; if (!open) return; $wire.refreshContext()"
        class="group relative rounded-full p-3.5 shadow-xl transition-all duration-200
            {{ $isLoading ? 'bg-violet-400 animate-pulse' : 'bg-violet-600 hover:bg-violet-700 hover:scale-105' }}"
        title="AI Assistant"
    >
        <span
            x-show="!open"
            x-transition:enter="transition duration-150"
            x-transition:enter-start="opacity-0 rotate-90"
            x-transition:enter-end="opacity-100 rotate-0"
        >
            <x-heroicon-o-sparkles class="h-5 w-5 text-white" />
        </span>
        <span
            x-show="open"
            x-transition:enter="transition duration-150"
            x-transition:enter-start="opacity-0 rotate-90"
            x-transition:enter-end="opacity-100 rotate-0"
        >
            <x-heroicon-o-chevron-down class="h-5 w-5 text-white" />
        </span>

        {{-- Unread indicator (shown when messages exist and panel closed) --}}
        @if (! $isOpen && count($messages) > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-300 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-violet-400"></span>
            </span>
        @endif
    </button>
</div>
