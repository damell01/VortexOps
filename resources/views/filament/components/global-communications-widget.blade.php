@php
    $viewer = auth()->user();
    $communicationsEnabled = \App\Services\TeamCommunications::canAccess($viewer);
    $quickThreads = $communicationsEnabled
        ? app(\App\Services\TeamCommunications::class)->inbox($viewer)->orderByDesc('last_message_at')->limit(5)->get()
        : collect();
    $quickUnread = $quickThreads->sum('unread_count');
@endphp
@if($communicationsEnabled)
<div class="vx-global-chat" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <button type="button" class="vx-global-chat-toggle" x-on:click="open = !open" :aria-expanded="open.toString()" aria-controls="vx-global-chat-panel" aria-label="Open messages">
        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" /><span>Messages</span>
        @if($quickUnread)<b>{{ $quickUnread }}</b>@endif
    </button>
    <section id="vx-global-chat-panel" class="vx-global-chat-panel" x-show="open" x-cloak x-transition.opacity aria-label="Quick messages">
        <header><strong>Messages</strong><button type="button" x-on:click="open = false" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button></header>
        <p class="vx-global-chat-note">Owner preview only · Employee delivery is off.</p>
        @forelse($quickThreads as $thread)
            <a class="vx-global-chat-thread" href="{{ \App\Filament\Pages\Communications::getUrl(panel: 'admin') }}">
                <span class="vx-global-chat-avatar"><x-filament::icon icon="heroicon-o-user-group" /></span>
                <span><strong>{{ $thread->labelFor($viewer) }}</strong><small>{{ \Illuminate\Support\Str::limit($thread->latestMessage?->body ?: 'Open conversation', 48) }}</small></span>
                @if($thread->unread_count)<b>{{ $thread->unread_count }}</b>@endif
            </a>
        @empty
            <p class="vx-global-chat-note">No conversations yet.</p>
        @endforelse
        <a class="vx-global-chat-all" href="{{ \App\Filament\Pages\Communications::getUrl(panel: 'admin') }}">Open communications</a>
    </section>
</div>
@endif
