@if(\App\Services\TeamCommunications::canAccess(auth()->user()))
    @if(config('chatify-preview.enabled', false))
        <div class="vx-chatify-shell" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <button type="button" class="vx-chatify-launch" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="vx-chatify-drawer" aria-label="Open messages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                <span>Messages</span>
            </button>
            <section id="vx-chatify-drawer" class="vx-chatify-drawer" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="Team messages">
                <header class="vx-chatify-drawer-head">
                    <div><strong>Team messages</strong><small>Chatify messenger · Dev preview</small></div>
                    <div class="vx-chatify-drawer-actions">
                        <a href="{{ url('/chatify') }}" target="_blank" rel="noopener" title="Open full messenger" aria-label="Open full messenger"><x-filament::icon icon="heroicon-o-arrows-pointing-out" /></a>
                        <button type="button" x-on:click="open = false" title="Close messages" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                    </div>
                </header>
                <iframe x-bind:src="open ? '{{ url('/chatify') }}' : 'about:blank'" title="Chatify team messenger" loading="lazy" referrerpolicy="same-origin"></iframe>
            </section>
        </div>
    @else
        <livewire:quick-messages />
    @endif
@endif
