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
                <form class="vx-global-chat-create vx-chat-imessage" wire:submit="createConversation">
                    <div class="vx-chat-to"><span>To:</span><input wire:model.live.debounce.250ms="peopleSearch" placeholder="Name or search team members…" aria-label="Find recipients" autocomplete="off"></div>
                    @if($selectedPeople->isNotEmpty())
                        <div class="vx-chat-chips" aria-label="Selected recipients">
                            @foreach($selectedPeople as $person)
                                <button type="button" class="vx-chat-chip" wire:key="recipient-{{ $person->id }}" wire:click="$set('members', {{ json_encode(array_values(array_diff(array_map('intval', $members), [$person->id]))) }})">{{ $person->name }} <span aria-hidden="true">×</span></button>
                            @endforeach
                        </div>
                    @endif
                    <div class="vx-chat-recipient-list">
                        @forelse($people as $person)
                            <label class="vx-chat-recipient" wire:key="candidate-{{ $person->id }}">
                                <span class="vx-chat-person-avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
                                <span>{{ $person->name }}</span>
                                <input type="checkbox" wire:model.live="members" value="{{ $person->id }}" aria-label="Add {{ $person->name }}">
                            </label>
                        @empty
                            <p class="vx-global-chat-note">No matching team members.</p>
                        @endforelse
                    </div>
                    <div class="vx-chat-role-select">
                        <label for="vx-quick-role">Add everyone in a role</label>
                        <select id="vx-quick-role" wire:model.live="selectedRoles" multiple size="3" aria-label="Add all users in selected roles">
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ str($role->name)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        <small>Choose a role to add its members. You can remove individual people above.</small>
                    </div>
                    @if(count($members) > 1)
                        <label class="vx-chat-group-name">Group name <span>(optional)</span><input wire:model="groupTitle" maxlength="120" placeholder="e.g. Show Operations"></label>
                    @endif
                    <p class="vx-chat-type-hint">{{ count($members) === 1 ? 'Direct message · 1 recipient' : (count($members) > 1 ? 'Group conversation · '.count($members).' recipients' : 'Select someone to start a message') }}</p>
                    @error('members')<p class="vx-chat-error" role="alert">{{ $message }}</p>@enderror
                    @error('groupTitle')<p class="vx-chat-error" role="alert">{{ $message }}</p>@enderror
                    <div class="vx-global-chat-create-actions"><button type="button" wire:click="$set('creating', false)">Cancel</button><button type="submit" @disabled(count($members) === 0)>Start chat <x-filament::icon icon="heroicon-o-arrow-right" /></button></div>
                </form>
            @elseif($selected)
                <div class="vx-global-chat-history" wire:poll.visible.15s>
                    @forelse($messages as $message)
                        <div class="vx-global-chat-bubble {{ $message->sender_id === auth()->id() ? 'is-own' : '' }}" wire:key="quick-message-{{ $message->id }}">
                            <small>{{ $message->sender?->name ?? 'Former member' }} · {{ $message->created_at->format('g:i A') }}</small>
                            <p>{{ $message->body }}</p>
                            @foreach($message->attachments as $file)<a class="vx-global-chat-file" href="{{ route('communications.attachment', $file->id) }}"><x-filament::icon icon="heroicon-o-document-arrow-down" /> {{ $file->name }}</a>@endforeach
                        </div>
                    @empty
                        <p class="vx-global-chat-note">No messages yet. Start the conversation below.</p>
                    @endforelse
                </div>
                <form class="vx-global-chat-compose" wire:submit="send">
                    <label class="sr-only" for="quick-message-body">Message</label>
                    <textarea id="quick-message-body" wire:model="body" maxlength="5000" rows="2" placeholder="Write a message…"></textarea>
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
