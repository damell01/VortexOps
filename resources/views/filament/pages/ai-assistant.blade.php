<x-filament-panels::page>
    <div
        x-data="{ loading: @entangle('isLoading') }"
        x-on:scroll-to-bottom.window="$nextTick(() => {
            const el = document.getElementById('chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        })"
        class="space-y-4"
    >

        {{-- ── Status bar ───────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2 text-sm">
            @if ($ollamaOnline)
                <span class="flex items-center gap-1.5 text-success-600 dark:text-success-400">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-success-500"></span>
                    </span>
                    Ollama online
                </span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500 dark:text-gray-400">Model: <span class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $ollamaModel }}</span></span>
            @else
                <span class="flex items-center gap-1.5 text-danger-600 dark:text-danger-400">
                    <span class="h-2 w-2 rounded-full bg-danger-500"></span>
                    Ollama offline
                </span>
                <span class="text-gray-400 text-xs">— run <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">ollama serve</code> to enable AI features</span>
            @endif
        </div>

        {{-- ── Quick actions ────────────────────────────────────────────── --}}
        <div class="flex flex-wrap gap-2">
            <button
                wire:click="runQuickAction('inventory_analysis')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-primary-300 dark:border-primary-600 bg-primary-50 dark:bg-primary-950 px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-primary-900 disabled:opacity-40 transition"
            >
                <x-heroicon-o-chart-bar-square class="h-3.5 w-3.5" />
                Inventory Health
            </button>
            <button
                wire:click="runQuickAction('reorder_suggestions')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-warning-300 dark:border-warning-600 bg-warning-50 dark:bg-warning-950 px-3 py-1.5 text-xs font-medium text-warning-700 dark:text-warning-300 hover:bg-warning-100 dark:hover:bg-warning-900 disabled:opacity-40 transition"
            >
                <x-heroicon-o-shopping-cart class="h-3.5 w-3.5" />
                Reorder Suggestions
            </button>
            <button
                wire:click="runQuickAction('movement_analysis')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-info-300 dark:border-info-600 bg-info-50 dark:bg-info-950 px-3 py-1.5 text-xs font-medium text-info-700 dark:text-info-300 hover:bg-info-100 dark:hover:bg-info-900 disabled:opacity-40 transition"
            >
                <x-heroicon-o-arrow-trending-up class="h-3.5 w-3.5" />
                Movement Analysis
            </button>
        </div>

        {{-- ── Chat window ──────────────────────────────────────────────── --}}
        <div
            id="chat-messages"
            class="flex flex-col gap-4 min-h-96 max-h-[36rem] overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-950 p-4"
        >
            @forelse ($messages as $msg)
                @if ($msg['role'] === 'user')
                    {{-- User bubble --}}
                    <div class="flex justify-end">
                        <div class="max-w-2xl">
                            <div class="rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm text-white shadow-sm">
                                {{ $msg['content'] }}
                            </div>
                            <div class="mt-1 text-right text-xs text-gray-400">{{ $msg['time'] }}</div>
                        </div>
                    </div>
                @else
                    {{-- AI bubble --}}
                    <div class="flex justify-start gap-2.5">
                        <div class="mt-1 flex-shrink-0 rounded-full bg-violet-100 dark:bg-violet-900 p-1.5">
                            <x-heroicon-o-sparkles class="h-3.5 w-3.5 text-violet-600 dark:text-violet-300" />
                        </div>
                        <div class="max-w-3xl">
                            <div class="rounded-2xl rounded-bl-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-sm shadow-sm
                                {{ isset($msg['success']) && ! $msg['success'] ? 'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-950' : '' }}">
                                @if (isset($msg['success']) && ! $msg['success'])
                                    <span class="text-danger-600 dark:text-danger-400">{{ $msg['content'] }}</span>
                                @else
                                    <div class="whitespace-pre-wrap text-gray-800 dark:text-gray-200">{{ $msg['content'] }}</div>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-400">
                                <span>{{ $msg['time'] }}</span>
                                @if (isset($msg['latency']) && $msg['latency'])
                                    <span>·</span>
                                    <span>{{ number_format($msg['latency'] / 1000, 1) }}s</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
                    <div class="rounded-full bg-violet-100 dark:bg-violet-900 p-4">
                        <x-heroicon-o-sparkles class="h-8 w-8 text-violet-500" />
                    </div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Ask anything about your inventory</p>
                    <p class="text-xs text-gray-400">Try a quick action above or type your own question below.</p>
                </div>
            @endforelse

            {{-- Typing indicator --}}
            @if ($isLoading)
                <div class="flex justify-start gap-2.5">
                    <div class="mt-1 flex-shrink-0 rounded-full bg-violet-100 dark:bg-violet-900 p-1.5">
                        <x-heroicon-o-sparkles class="h-3.5 w-3.5 text-violet-600 dark:text-violet-300" />
                    </div>
                    <div class="rounded-2xl rounded-bl-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3 shadow-sm">
                        <div class="flex items-center gap-1">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Input area ───────────────────────────────────────────────── --}}
        <form wire:submit="sendMessage" class="flex gap-2">
            <input
                wire:model="question"
                type="text"
                placeholder="Ask about your inventory…"
                autocomplete="off"
                :disabled="loading"
                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-none disabled:opacity-50"
            />
            <button
                type="submit"
                :disabled="loading"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 transition"
            >
                <x-heroicon-o-paper-airplane class="h-4 w-4" />
                Send
            </button>
        </form>

    </div>
</x-filament-panels::page>
