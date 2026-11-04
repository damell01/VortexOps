<div class="vx-global-chat" x-data x-on:keydown.escape.window="$wire.open = false">
    <button type="button" class="vx-global-chat-toggle" wire:click="$toggle('open')" aria-controls="vx-global-chat-panel" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="Open quick messages">
        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" /><span>Messages</span>
        @if($threads->sum('unread_count'))<b>{{ $threads->sum('unread_count') }}</b>@endif
    </button>
    @if($open)
        <section id="vx-global-chat-panel" class="vx-global-chat-panel" aria-label="Quick messages">
            <header>
                @if($selected)<button type="button" wire:click="back" aria-label="Back to conversations"><x-filament::icon icon="heroicon-o-arrow-left" /></button>@endif
                <strong>{{ $selected ? $selected->labelFor(auth()->user()) : ($creating ? 'New conversation' : 'Messages') }}</strong>
                @if(!$selected && !$creating)<button type="button" wire:click="$set('creating', true)" aria-label="New conversation"><x-filament::icon icon="heroicon-o-plus" /></button>@endif
                <button type="button" wire:click="$set('open', false)" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
            </header>
            <p class="vx-global-chat-note">Owner preview only · Employee delivery is off.</p>
            @if($creating && !$selected)
                <form class="vx-global-chat-create" wire:submit="createConversation">
                    <label>Conversation type<select wire:model.live="kind"><option value="direct">Direct message</option><option value="group">Group chat</option></select></label>
                    @if($kind === 'group')<label>Group name<input wire:model="groupTitle" maxlength="120" required placeholder="Group name"></label>@endif
                    <label>Find people<input wire:model.live.debounce.300ms="peopleSearch" placeholder="Search team members"></label>
                    <fieldset><legend>{{ $kind === 'direct' ? 'Choose one person' : 'Choose group members' }}</legend>
                        @foreach($people as $person)<label class="vx-global-chat-person"><input type="checkbox" wire:model="members" value="{{ $person->id }}">{{ $person->name }}</label>@endforeach
                    </fieldset>
                    @error('members')<p role="alert">{{ $message }}</p>@enderror
                    @error('groupTitle')<p role="alert">{{ $message }}</p>@enderror
                    <div class="vx-global-chat-create-actions"><button type="button" wire:click="$set('creating', false)">Cancel</button><button type="submit">Create conversation</button></div>
                </form>
            @elseif($selected)
                <div class="vx-global-chat-history" wire:poll.visible.15s>
                    @forelse($messages as $message)
                        <div class="vx-global-chat-bubble {{ $message->sender_id === auth()->id() ? 'is-own' : '' }}" wire:key="quick-message-{{ $message->id }}">
                            <small>{{ $message->sender?->name ?? 'Former member' }} · {{ $message->created_at->format('g:i A') }}</small>
                            <p>{{ $message->body }}</p>
                        </div>
                    @empty
                        <p class="vx-global-chat-note">No messages yet. Start the conversation below.</p>
                    @endforelse
                </div>
                <form class="vx-global-chat-compose" wire:submit="send">
                    <label class="sr-only" for="quick-message-body">Message</label>
                    <textarea id="quick-message-body" wire:model="body" maxlength="5000" rows="2" placeholder="Write a message…" required></textarea>
                    @error('body')<p role="alert">{{ $message }}</p>@enderror
                    <label class="vx-global-chat-attach" title="Attach files"><x-filament::icon icon="heroicon-o-paper-clip" /><input type="file" wire:model="attachments" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv" aria-label="Attach files"></label>
                    <button type="submit" wire:loading.attr="disabled" wire:target="send" aria-label="Send message"><x-filament::icon icon="heroicon-o-paper-airplane" /></button>
                </form>
                @if(count($attachments))<p class="vx-global-chat-note">{{ count($attachments) }} file(s) attached</p>@endif
                @error('attachments.*')<p role="alert">{{ $message }}</p>@enderror
            @else
                <div class="vx-global-chat-list" wire:poll.visible.30s>
                    @forelse($threads as $thread)
                        <button type="button" class="vx-global-chat-thread" wire:click="openConversation({{ $thread->id }})">
                            <span class="vx-global-chat-avatar"><x-filament::icon icon="heroicon-o-user-group" /></span>
                            <span><strong>{{ $thread->labelFor(auth()->user()) }}</strong><small>{{ \Illuminate\Support\Str::limit($thread->latestMessage?->body ?: 'Open conversation', 48) }}</small></span>
                            @if($thread->unread_count)<b>{{ $thread->unread_count }}</b>@endif
                        </button>
                    @empty
                        <p class="vx-global-chat-note">No conversations yet.</p>
                    @endforelse
                </div>
                <a class="vx-global-chat-all" href="{{ \App\Filament\Pages\Communications::getUrl(panel: 'admin') }}">Open communications</a>
            @endif
        </section>
    @endif
</div>
