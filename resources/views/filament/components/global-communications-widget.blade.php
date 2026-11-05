@if(\App\Services\TeamCommunications::canAccess(auth()->user()))
    @if(config('chatify-preview.enabled', false))
        {{-- Native shell opens instantly. Chatify remains available in a full tab while API payloads are verified. --}}
        <div class="vx-chatify-shell" x-data="{
            open:false, loaded:false, loading:false, error:'', threads:[], search:'',
            async load() {
                if (this.loading) return;
                this.loading=true; this.error='';
                try {
                    const response=await fetch('{{ url('/api/chatify/v1/conversations') }}', {
                        credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
                    });
                    if (!response.ok) throw new Error('Messenger returned HTTP '+response.status);
                    const payload=await response.json();
                    const candidates=[payload?.data?.data,payload?.data?.conversations,payload?.conversations,payload?.data,payload?.results,payload];
                    const list=candidates.find(Array.isArray);
                    if (!list) throw new Error('Conversation response format needs mapping');
                    this.threads=list; this.loaded=true;
                } catch(e) { this.error=e.message || 'Could not load conversations'; }
                finally { this.loading=false; }
            },
            toggle() { this.open=!this.open; if(this.open && !this.loaded) this.load(); },
            label(t) { return t?.name || t?.title || t?.display_name || t?.other_user?.name || t?.user?.name || 'Conversation'; },
            snippet(t) { return t?.last_message?.body || t?.last_message?.message || t?.latest_message?.body || 'Open conversation'; },
            identifier(t) { return t?.uuid || t?.id || t?.conversation_id || ''; }
        }" x-on:keydown.escape.window="open = false">
            <button type="button" class="vx-chatify-launch" x-on:click="toggle()" x-bind:aria-expanded="open.toString()" aria-controls="vx-chatify-drawer" aria-label="Open messages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9L13 3a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                <span>Messages</span>
            </button>
            <section id="vx-chatify-drawer" class="vx-chatify-drawer vx-chatify-native" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="Team messages">
                <header class="vx-chatify-drawer-head">
                    <div><strong>Messages</strong><small>Vortex Ops team chat</small></div>
                    <div class="vx-chatify-drawer-actions">
                        <button type="button" x-on:click="load()" title="Refresh conversations" aria-label="Refresh conversations"><x-filament::icon icon="heroicon-o-arrow-path" /></button>
                        <a href="{{ url('/chatify') }}" target="_blank" rel="noopener" title="Open full messenger" aria-label="Open full messenger"><x-filament::icon icon="heroicon-o-arrows-pointing-out" /></a>
                        <button type="button" x-on:click="open = false" title="Close messages" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                    </div>
                </header>
                <div class="vx-chatify-native-body">
                    <div class="vx-chatify-native-search"><input type="search" x-model="search" placeholder="Search conversations…" aria-label="Search conversations"></div>
                    <p class="vx-chatify-native-hint" x-show="loading">Loading conversations…</p>
                    <div class="vx-chatify-native-error" x-show="error">
                        <p x-text="error"></p>
                        <a href="{{ url('/chatify') }}" target="_blank" rel="noopener">Continue in Chatify</a>
                    </div>
                    <template x-if="loaded && !loading && !threads.length"><p class="vx-chatify-native-hint">No conversations yet. Start one in the full messenger.</p></template>
                    <template x-for="(thread,index) in threads.filter(t => label(t).toLowerCase().includes(search.toLowerCase()))" :key="identifier(thread) || index">
                        <a class="vx-chatify-native-thread" :href="'{{ url('/chatify') }}/'+encodeURIComponent(identifier(thread))" target="_blank" rel="noopener">
                            <span class="vx-chatify-native-avatar" x-text="label(thread).charAt(0).toUpperCase()"></span>
                            <span class="vx-chatify-native-thread-copy"><strong x-text="label(thread)"></strong><small x-text="snippet(thread)"></small></span>
                            <x-filament::icon icon="heroicon-m-chevron-right" />
                        </a>
                    </template>
                </div>
                <footer class="vx-chatify-native-footer"><a href="{{ url('/chatify') }}" target="_blank" rel="noopener">New message or group chat <span aria-hidden="true">＋</span></a></footer>
            </section>
        </div>
    @else
        <livewire:quick-messages />
    @endif
@endif
