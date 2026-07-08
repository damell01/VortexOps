<div>
@if($aiEnabled)
<div
    x-data="{
        open: false,
        pendingMsg: '',
        localPending: false,
        init() {
            this.$wire.setPath(window.location.pathname);
            document.addEventListener('livewire:navigate', () => {
                this.$wire.setPath(window.location.pathname);
            });
        },
        handleSend() {
            let text = this.$refs.msgInput.value.trim();
            if (!text || this.$wire.thinking || this.localPending) return;
            this.pendingMsg = text;
            this.localPending = true;
            this.$nextTick(() => {
                let el = document.getElementById('ai-messages');
                if (el) el.scrollTop = el.scrollHeight;
            });
            this.$wire.send();
        }
    }"
    x-effect="
        if ($wire.thinking) { pendingMsg = ''; }
        if (!$wire.thinking) { localPending = false; }
    "
    x-on:ai-scroll-bottom.window="
        $nextTick(() => {
            let el = document.getElementById('ai-messages');
            if (el) el.scrollTop = el.scrollHeight;
        })
    "
    style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:0.5rem"
>
    {{-- Toggle button --}}
    <button
        x-on:click="open = !open"
        title="Vortex AI"
        style="
            display:flex;align-items:center;gap:0.4rem;
            background:#7c3aed;color:#fff;border:none;border-radius:9999px;
            padding:0.55rem 1rem;font-size:0.8rem;font-weight:600;
            cursor:pointer;box-shadow:0 4px 14px rgba(124,58,237,.45);
            transition:background .15s;
        "
        onmouseover="this.style.background='#6d28d9'"
        onmouseout="this.style.background='#7c3aed'"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:1rem;height:1rem">
            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5Z" clip-rule="evenodd" />
        </svg>
        <span x-text="open ? 'Close AI' : 'Vortex AI'"></span>
    </button>

    {{-- Chat panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        style="
            width:22rem;max-width:calc(100vw - 2rem);
            background:#fff;border-radius:0.75rem;
            box-shadow:0 10px 40px rgba(0,0,0,.18);
            border:1px solid #e5e7eb;
            display:flex;flex-direction:column;overflow:hidden;
        "
    >
        {{-- Header --}}
        <div style="background:#7c3aed;padding:0.65rem 1rem;display:flex;align-items:center;justify-content:space-between">
            <span style="color:#fff;font-size:0.85rem;font-weight:700;letter-spacing:.01em">Vortex AI</span>
            <button
                wire:click="clear"
                title="Clear chat"
                style="background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:0.375rem;padding:0.2rem 0.5rem;font-size:0.7rem;cursor:pointer"
            >Clear</button>
        </div>

        {{-- Messages --}}
        <div
            id="ai-messages"
            style="flex:1;overflow-y:auto;padding:0.75rem;display:flex;flex-direction:column;gap:0.5rem;max-height:22rem;min-height:5rem"
        >
            @forelse($messages as $msg)
                <div style="display:flex;{{ $msg['role'] === 'user' ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
                    <div style="
                        max-width:85%;padding:0.45rem 0.7rem;border-radius:0.6rem;font-size:0.78rem;line-height:1.45;white-space:pre-wrap;word-break:break-word;
                        {{ $msg['role'] === 'user'
                            ? 'background:#7c3aed;color:#fff;border-bottom-right-radius:0.2rem'
                            : 'background:#f3f4f6;color:#111827;border-bottom-left-radius:0.2rem' }}
                    ">{{ $msg['content'] }}</div>
                </div>
            @empty
                <p style="color:#9ca3af;font-size:0.78rem;text-align:center;margin-top:1rem">
                    Ask me anything about your shows, inventory, payouts, or streamers.
                </p>
            @endforelse

            {{-- Optimistic user bubble: shown immediately on send, before server confirms --}}
            <template x-if="pendingMsg">
                <div style="display:flex;justify-content:flex-end">
                    <div style="max-width:85%;padding:0.45rem 0.7rem;border-radius:0.6rem;border-bottom-right-radius:0.2rem;font-size:0.78rem;line-height:1.45;white-space:pre-wrap;word-break:break-word;background:#7c3aed;color:#fff" x-text="pendingMsg"></div>
                </div>
            </template>

            {{-- Local thinking dots: shown immediately while request is in flight --}}
            <template x-if="localPending && !$wire.thinking">
                <div style="display:flex;justify-content:flex-start">
                    <div style="background:#f3f4f6;border-radius:0.6rem;border-bottom-left-radius:0.2rem;padding:0.5rem 0.75rem;display:flex;gap:4px;align-items:center">
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite"></span>
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite .2s"></span>
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite .4s"></span>
                    </div>
                </div>
            </template>

            {{-- Live streaming bubble (server is responding) --}}
            @if($thinking)
                <div
                    x-data="{ started: false }"
                    x-on:livewire-stream.window="started = true; $nextTick(() => { let el = document.getElementById('ai-messages'); if(el) el.scrollTop = el.scrollHeight; })"
                    style="display:flex;justify-content:flex-start"
                >
                    <div
                        x-show="!started"
                        style="background:#f3f4f6;border-radius:0.6rem;border-bottom-left-radius:0.2rem;padding:0.5rem 0.75rem;display:flex;gap:4px;align-items:center"
                    >
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite"></span>
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite .2s"></span>
                        <span style="width:6px;height:6px;background:#9ca3af;border-radius:9999px;display:inline-block;animation:ai-bounce 1s infinite .4s"></span>
                    </div>
                    <div
                        x-show="started"
                        wire:stream="streamingResponse"
                        style="max-width:85%;padding:0.45rem 0.7rem;border-radius:0.6rem;border-bottom-left-radius:0.2rem;font-size:0.78rem;line-height:1.45;white-space:pre-wrap;word-break:break-word;background:#f3f4f6;color:#111827"
                    >{{ $streamingResponse }}</div>
                </div>
            @endif
        </div>

        {{-- Input --}}
        <form
            x-on:submit.prevent="handleSend()"
            style="border-top:1px solid #e5e7eb;padding:0.6rem;display:flex;gap:0.4rem"
        >
            <input
                x-ref="msgInput"
                wire:model="input"
                type="text"
                placeholder="Ask Vortex AI…"
                autocomplete="off"
                x-bind:disabled="$wire.thinking || localPending"
                x-bind:style="`flex:1;border:1px solid #d1d5db;border-radius:0.5rem;padding:0.4rem 0.65rem;font-size:0.8rem;outline:none;background:${($wire.thinking || localPending) ? '#f9fafb' : '#fff'}`"
            >
            <button
                type="submit"
                x-bind:disabled="$wire.thinking || localPending"
                x-bind:style="`background:#7c3aed;color:#fff;border:none;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:0.8rem;font-weight:600;cursor:${($wire.thinking || localPending) ? 'not-allowed' : 'pointer'};opacity:${($wire.thinking || localPending) ? '.6' : '1'}`"
            >Send</button>
        </form>
    </div>
</div>

<style>
@keyframes ai-bounce {
    0%,100% { transform:translateY(0); }
    50%      { transform:translateY(-4px); }
}
</style>
@endif
</div>
