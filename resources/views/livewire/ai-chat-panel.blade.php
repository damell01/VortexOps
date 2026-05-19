<div
    x-data="{
        open: @entangle('isOpen').live,
        pendingMessage: '',
        pendingElapsed: 0,
        pendingTimer: null,
        streamStarted: false,
        beginPending(message) {
            this.pendingMessage = message;
            this.pendingElapsed = 0;
            this.streamStarted  = false;
            clearInterval(this.pendingTimer);
            this.pendingTimer = setInterval(() => this.pendingElapsed++, 1000);
        },
        clearPending() {
            this.pendingMessage = '';
            this.pendingElapsed = 0;
            this.streamStarted  = false;
            clearInterval(this.pendingTimer);
            this.pendingTimer = null;
        },
    }"
    x-on:message-received.window="clearPending()"
    x-on:livewire:dispatched.window="
        if ($event.detail.name === 'ai-stream-started') streamStarted = true
    "
    x-on:panel-scroll-to-bottom.window="$nextTick(() => {
        const el = document.getElementById('ai-panel-messages');
        if (el) el.scrollTop = el.scrollHeight;
    })"
    class="fixed bottom-6 right-6 z-[99999] flex flex-col items-end gap-2"
>
    {{-- Panel --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-2 scale-95 opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 scale-100 opacity-100"
        x-transition:leave-end="translate-y-2 scale-95 opacity-0"
        class="flex w-96 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
        style="height: 520px"
    >
        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between bg-violet-600 px-4 py-2.5 text-white">
            <div class="flex items-center gap-2">
                <x-heroicon-o-sparkles class="h-4 w-4" />
                <span class="text-sm font-semibold">Vortex Assistant</span>
                @if ($ollamaOnline)
                    <span class="flex h-1.5 w-1.5 rounded-full bg-green-400"></span>
                @else
                    <span class="flex h-1.5 w-1.5 rounded-full bg-red-400"></span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="clearChat" title="Clear chat" class="opacity-60 transition hover:opacity-100">
                    <x-heroicon-o-trash class="h-3.5 w-3.5" />
                </button>
                <button wire:click="refreshContext" title="Refresh context" class="opacity-60 transition hover:opacity-100">
                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                </button>
                <button @click="open = false" class="opacity-60 transition hover:opacity-100">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </button>
            </div>
        </div>

        {{-- Context pill --}}
        @if ($contextLabel && $contextLabel !== 'VortexOps')
            <div class="flex shrink-0 items-center gap-1.5 border-b border-violet-100 bg-violet-50 px-4 py-1.5 dark:border-violet-900 dark:bg-violet-950">
                <x-heroicon-o-eye class="h-3 w-3 text-violet-500" />
                <span class="truncate text-xs text-violet-700 dark:text-violet-300">Context: <strong>{{ $contextLabel }}</strong></span>
            </div>
        @endif

        {{-- Messages --}}
        <div id="ai-panel-messages" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 p-3 dark:bg-gray-950">

            {{-- Confirmed messages --}}
            @forelse ($messages as $msg)
                @if ($msg['role'] === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-br-sm bg-violet-600 px-3 py-2 text-xs text-white shadow-sm">
                                {{ $msg['content'] }}
                            </div>
                            <div class="mt-0.5 text-right text-[10px] text-gray-400">{{ $msg['time'] }}</div>
                        </div>
                    </div>
                @else
                    <div class="flex justify-start gap-2">
                        <div class="mt-0.5 shrink-0 rounded-full bg-violet-100 p-1 dark:bg-violet-900">
                            <x-heroicon-o-sparkles class="h-3 w-3 text-violet-500" />
                        </div>
                        <div class="max-w-[85%]">
                            <div class="rounded-2xl rounded-bl-sm border px-3 py-2 text-xs shadow-sm {{ isset($msg['success']) && ! $msg['success'] ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300' : 'border-gray-200 bg-white text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">
                                <div class="whitespace-pre-wrap">{{ $msg['content'] }}</div>
                            </div>
                            <div class="mt-0.5 text-[10px] text-gray-400">
                                {{ $msg['time'] }}
                                @if (! empty($msg['latency']))
                                    · {{ number_format($msg['latency'] / 1000, 1) }}s
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex h-full flex-col items-center justify-center gap-2 py-8 text-center">
                    <x-heroicon-o-sparkles class="h-8 w-8 text-violet-300" />
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Ask anything about your data</p>
                    @if ($contextLabel && $contextLabel !== 'VortexOps')
                        <p class="text-[10px] text-gray-400">Viewing <strong>{{ $contextLabel }}</strong></p>
                    @endif
                </div>
            @endforelse

            {{-- Live streaming response (appears token-by-token while action runs) --}}
            <div
                x-show="pendingMessage && streamStarted"
                x-cloak
                class="flex justify-start gap-2"
            >
                <div class="mt-0.5 shrink-0 rounded-full bg-violet-100 p-1 dark:bg-violet-900">
                    <x-heroicon-o-sparkles class="h-3 w-3 text-violet-500" />
                </div>
                <div class="max-w-[85%]">
                    <div class="rounded-2xl rounded-bl-sm border border-gray-200 bg-white px-3 py-2 text-xs text-gray-800 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <div wire:stream="aiStream" class="whitespace-pre-wrap"></div>
                    </div>
                    <div class="mt-0.5 text-[10px] text-gray-400">Generating…</div>
                </div>
            </div>

            {{-- Optimistic: user message + typing indicator (shown before first stream token) --}}
            <template x-if="pendingMessage">
                <div class="space-y-3">
                    <div class="flex justify-end">
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-br-sm bg-violet-600 px-3 py-2 text-xs text-white shadow-sm" x-text="pendingMessage"></div>
                            <div class="mt-0.5 text-right text-[10px] text-gray-400">Sending…</div>
                        </div>
                    </div>

                    <div class="flex justify-start gap-2" x-show="!streamStarted">
                        <div class="mt-0.5 shrink-0 rounded-full bg-violet-100 p-1 dark:bg-violet-900">
                            <x-heroicon-o-sparkles class="h-3 w-3 text-violet-500" />
                        </div>
                        <div class="max-w-[85%]">
                            <div class="rounded-2xl rounded-bl-sm border border-gray-200 bg-white px-3 py-2.5 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-400" style="animation-delay:0ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-400" style="animation-delay:150ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-violet-400" style="animation-delay:300ms"></span>
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400">
                                        Thinking
                                        <span x-show="pendingElapsed >= 5" x-text="' · ' + pendingElapsed + 's'"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input --}}
        <div class="shrink-0 border-t border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900">
            <form
                wire:submit="sendMessage"
                x-on:submit="
                    const q = $refs.question.value.trim();
                    if (q) { beginPending(q); $nextTick(() => $refs.question.value = ''); }
                "
                class="flex gap-1.5"
            >
                <input
                    x-ref="question"
                    wire:model="question"
                    type="text"
                    placeholder="{{ $ollamaOnline ? 'Ask Vortex Assistant…' : 'Ollama offline' }}"
                    :disabled="!!pendingMessage"
                    {{ ! $ollamaOnline ? 'disabled' : '' }}
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-1.5 text-xs text-gray-900 placeholder-gray-400 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
                <button
                    type="submit"
                    :disabled="!!pendingMessage"
                    {{ ! $ollamaOnline ? 'disabled' : '' }}
                    class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-white transition hover:bg-violet-700 disabled:opacity-50"
                >
                    <x-heroicon-o-paper-airplane class="h-3.5 w-3.5" />
                </button>
            </form>
        </div>
    </div>

    {{-- FAB --}}
    <button
        @click="open = !open; if (open) $wire.refreshContext()"
        class="group relative rounded-full bg-violet-600 p-3.5 shadow-xl transition-all duration-200 hover:scale-105 hover:bg-violet-700"
        :class="{ 'animate-pulse': !!pendingMessage }"
        title="Vortex Assistant"
    >
        <span x-show="!open" x-transition:enter="transition duration-150" x-transition:enter-start="rotate-90 opacity-0" x-transition:enter-end="rotate-0 opacity-100">
            <x-heroicon-o-sparkles class="h-5 w-5 text-white" />
        </span>
        <span x-show="open" x-transition:enter="transition duration-150" x-transition:enter-start="rotate-90 opacity-0" x-transition:enter-end="rotate-0 opacity-100">
            <x-heroicon-o-chevron-down class="h-5 w-5 text-white" />
        </span>

        @if (! $isOpen && count($messages) > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-300 opacity-75"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-violet-400"></span>
            </span>
        @endif
    </button>
</div>
