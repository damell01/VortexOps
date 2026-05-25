<x-filament-panels::page>
    <div
        x-data="{
            loading: @entangle('isLoading'),
            pendingMessage: '',
            pendingElapsed: 0,
            pendingTimer: null,
            beginPending(message) {
                this.pendingMessage = message;
                this.pendingElapsed = 0;
                clearInterval(this.pendingTimer);
                this.pendingTimer = setInterval(() => {
                    this.pendingElapsed += 1;
                }, 1000);
            },
            clearPending() {
                this.pendingMessage = '';
                this.pendingElapsed = 0;
                clearInterval(this.pendingTimer);
                this.pendingTimer = null;
            },
        }"
        x-effect="if (!loading && pendingMessage) clearPending()"
        x-on:scroll-to-bottom.window="$nextTick(() => {
            const el = document.getElementById('chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        })"
        class="space-y-4"
    >
        <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-3">
                @if ($ollamaOnline)
                    <span class="flex items-center gap-1.5 text-success-600 dark:text-success-400">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                        </span>
                        Ollama online
                    </span>
                @else
                    <span class="flex items-center gap-1.5 text-danger-600 dark:text-danger-400">
                        <span class="h-2 w-2 rounded-full bg-danger-500"></span>
                        Ollama offline
                    </span>
                @endif

                <span class="text-gray-400">·</span>
                <span class="text-gray-500 dark:text-gray-400">
                    Model:
                    <span class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $ollamaModel }}</span>
                </span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500 dark:text-gray-400">
                    URL:
                    <span class="font-mono text-xs text-gray-700 dark:text-gray-200">{{ $ollamaBaseUrl }}</span>
                </span>
            </div>

            @if (! empty($availableModels))
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Available models: {{ implode(', ', $availableModels) }}
                </div>
            @elseif (! $ollamaOnline)
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Run <code class="rounded bg-gray-100 px-1 font-mono dark:bg-gray-800">ollama serve</code> to enable AI features.
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                wire:click="runQuickAction('inventory_analysis')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-primary-300 bg-primary-50 px-3 py-1.5 text-xs font-medium text-primary-700 transition hover:bg-primary-100 disabled:opacity-40 dark:border-primary-600 dark:bg-primary-950 dark:text-primary-300 dark:hover:bg-primary-900"
            >
                <x-heroicon-o-chart-bar-square class="h-3.5 w-3.5" />
                Inventory Health
            </button>
            <button
                wire:click="runQuickAction('reorder_suggestions')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-warning-300 bg-warning-50 px-3 py-1.5 text-xs font-medium text-warning-700 transition hover:bg-warning-100 disabled:opacity-40 dark:border-warning-600 dark:bg-warning-950 dark:text-warning-300 dark:hover:bg-warning-900"
            >
                <x-heroicon-o-shopping-cart class="h-3.5 w-3.5" />
                Reorder Suggestions
            </button>
            <button
                wire:click="runQuickAction('movement_analysis')"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-info-300 bg-info-50 px-3 py-1.5 text-xs font-medium text-info-700 transition hover:bg-info-100 disabled:opacity-40 dark:border-info-600 dark:bg-info-950 dark:text-info-300 dark:hover:bg-info-900"
            >
                <x-heroicon-o-arrow-trending-up class="h-3.5 w-3.5" />
                Movement Analysis
            </button>
        </div>

        <div
            id="chat-messages"
            class="flex max-h-[36rem] min-h-96 flex-col gap-4 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950"
        >
            @forelse ($messages as $msg)
                @if ($msg['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-2xl">
                            <div class="rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm text-white shadow-sm">
                                {{ $msg['content'] }}
                            </div>
                            <div class="mt-1 text-right text-xs text-gray-400">{{ $msg['time'] }}</div>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start gap-2.5">
                        <div class="mt-1 flex-shrink-0 rounded-full bg-violet-100 p-1.5 dark:bg-violet-900">
                            <x-heroicon-o-sparkles class="h-3.5 w-3.5 text-violet-600 dark:text-violet-300" />
                        </div>
                        <div class="max-w-3xl">
                            <div class="rounded-2xl rounded-bl-sm border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 {{ isset($msg['success']) && ! $msg['success'] ? 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' : '' }}">
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
                    <div class="rounded-full bg-violet-100 p-4 dark:bg-violet-900">
                        <x-heroicon-o-sparkles class="h-8 w-8 text-violet-500" />
                    </div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Ask anything about your inventory</p>
                    <p class="text-xs text-gray-400">Try a quick action above or type your own question below.</p>
                </div>
            @endforelse

            <template x-if="pendingMessage">
                <div class="space-y-4">
                    <div class="flex justify-end">
                        <div class="max-w-2xl">
                            <div class="rounded-2xl rounded-br-sm bg-primary-600 px-4 py-2.5 text-sm text-white shadow-sm" x-text="pendingMessage"></div>
                            <div class="mt-1 text-right text-xs text-gray-400">Sending…</div>
                        </div>
                    </div>

                    <div class="flex justify-start gap-2.5">
                        <div class="mt-1 flex-shrink-0 rounded-full bg-violet-100 p-1.5 dark:bg-violet-900">
                            <x-heroicon-o-sparkles class="h-3.5 w-3.5 text-violet-600 dark:text-violet-300" />
                        </div>
                        <div class="max-w-3xl">
                            <div class="rounded-2xl rounded-bl-sm border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Vortex Assistant is thinking...
                                        <span x-show="pendingElapsed >= 8" x-text="' ' + pendingElapsed + 's'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <form
            wire:submit="sendMessage"
            x-on:submit="if ($refs.question.value.trim()) beginPending($refs.question.value.trim())"
            class="flex gap-2"
        >
            <input
                x-ref="question"
                wire:model="question"
                type="text"
                placeholder="Ask Vortex Assistant about your inventory..."
                autocomplete="off"
                :disabled="loading"
                class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            />
            <button
                type="submit"
                :disabled="loading"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50"
            >
                <x-heroicon-o-paper-airplane class="h-4 w-4" />
                Send
            </button>
        </form>
    </div>
</x-filament-panels::page>
