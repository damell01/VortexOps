<x-filament-panels::page>
<div class="vxc" data-vx-page="communications">
    <div class="vxc-preview"><x-filament::icon icon="heroicon-o-lock-closed" /><div><strong>Owner preview</strong><p>Only you can open this workspace. Selected people cannot see these conversations or announcements. Email, push and recipient notifications are paused.</p></div></div>
    <div class="vxc-tabs" role="group" aria-label="Communication workspace">
        <button type="button" wire:click="changeTab('messages')" aria-pressed="{{ $tab === 'messages' ? 'true' : 'false' }}"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" />Messages</button>
        <button type="button" wire:click="changeTab('announcements')" aria-pressed="{{ $tab === 'announcements' ? 'true' : 'false' }}"><x-filament::icon icon="heroicon-o-megaphone" />Announcements</button>
    </div>
    @if ($tab === 'messages')
        <div class="vxc-inbox" wire:poll.visible.15s>
            <section class="vxc-threads" aria-label="Conversations">
                <div class="vxc-section-head"><h2>Inbox</h2><x-filament::button size="sm" icon="heroicon-o-plus" x-on:click="$dispatch('open-modal', {id: 'communication-new-chat'})">New chat</x-filament::button></div>
                @forelse ($threads as $thread)
                    <button type="button" wire:click="selectConversation({{ $thread->id }})" class="vxc-thread {{ $conversationId === $thread->id ? 'is-selected' : '' }}" @if ($conversationId === $thread->id) aria-current="true" @endif wire:key="thread-{{ $thread->id }}">
                        <span class="vxc-avatar"><x-filament::icon :icon="$thread->kind === 'group' ? 'heroicon-o-user-group' : 'heroicon-o-user'" /></span><span><b>{{ $thread->labelFor(auth()->user()) }}</b><small>{{ $thread->kind === 'group' ? $thread->members->count().' members' : 'Direct chat' }} · {{ $thread->messages_count }} messages</small></span>
                    </button>
                @empty
                    <div class="vxc-empty"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /><h3>A place for your team</h3><p>Start a direct conversation or a named group to try the messaging flow.</p></div>
                @endforelse
                @if ($threads->hasPages())
                    <div class="vxc-pages"><button type="button" wire:click="paginateSection('threadPage', {{ max(1, $threadPage - 1) }})" @disabled($threads->onFirstPage())>Previous</button><span>{{ $threads->currentPage() }} / {{ $threads->lastPage() }}</span><button type="button" wire:click="paginateSection('threadPage', {{ $threadPage + 1 }})" @disabled(! $threads->hasMorePages())>Next</button></div>
                @endif
            </section>
            <section class="vxc-conversation" aria-label="Message thread">
                @if ($selected)
                    <div class="vxc-section-head"><div><h2>{{ $selected->labelFor(auth()->user()) }}</h2><p>{{ $selected->members->pluck('name')->implode(', ') }}</p></div><span class="vxc-badge">Preview only</span></div>
                    <div class="vxc-history" aria-label="Messages" wire:key="history-{{ $conversationId }}-{{ $messagePage }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" x-on:communication-message-saved.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                        @if ($messages->hasPages())<div class="vxc-pages"><button type="button" wire:click="paginateSection('messagePage', {{ $messagePage + 1 }})" @disabled(! $messages->hasMorePages())>Older messages</button><span>{{ $messages->total() }} total</span><button type="button" wire:click="paginateSection('messagePage', {{ max(1, $messagePage - 1) }})" @disabled($messages->onFirstPage())>Newer</button></div>@endif
                        @forelse ($messages->getCollection()->reverse() as $message)
                            <article class="vxc-message {{ $message->sender_id === auth()->id() ? 'is-own' : '' }}" wire:key="message-{{ $message->id }}"><div><b>{{ $message->sender?->name ?? 'Deleted account' }}</b><time datetime="{{ $message->created_at->toIso8601String() }}">{{ \App\Support\BusinessTime::local($message->created_at)->format('M j, g:i a') }}</time></div><p>{{ $message->body }}</p></article>
                        @empty
                            <div class="vxc-empty"><h3>Start the conversation</h3><p>Messages saved here remain in your owner preview.</p></div>
                        @endforelse
                    </div>
                    <form wire:submit="sendMessage" class="vxc-composer">
                        <label for="vxc-message-body" class="sr-only">Write a message</label><textarea id="vxc-message-body" wire:model="body" rows="2" maxlength="5000" placeholder="Write a preview message…" required></textarea>
                        @error('body')<p class="vxc-error" role="alert">{{ $message }}</p>@enderror
                        <div><small>Private owner sandbox · No delivery</small><x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendMessage">Save preview message</x-filament::button></div>
                    </form>
                @else
                    <div class="vxc-empty vxc-start"><x-filament::icon icon="heroicon-o-chat-bubble-oval-left-ellipsis" /><h3>Keep the conversation in Vortex Ops</h3><p>Select a chat, or start one with a person or group. Everything stays private to your owner account while we build.</p></div>
                @endif
            </section>
        </div>
    @else
        <section class="vxc-announcements" aria-label="Announcements">
            <div class="vxc-section-head"><div><h2>Announcements</h2><p>Draft updates, choose an audience, and schedule a banner preview.</p></div><x-filament::button icon="heroicon-o-plus" wire:click="newAnnouncement">New announcement</x-filament::button></div>
            @forelse ($announcements as $item)
                <article class="vxc-announcement" wire:key="announcement-{{ $item->id }}">
                    <div class="vxc-announcement-head"><h3>{{ $item->title }}</h3><span class="vxc-badge">{{ $item->phase() }}</span></div>
                    <p>{{ $item->body }}</p>
                    <div class="vxc-announcement-meta"><span>{{ $item->audience === 'all' ? 'Everyone' : ($item->audience === 'roles' ? 'Selected roles' : 'Selected people') }} · {{ $audienceCounts[$item->id] }} intended recipients</span><span>{{ \App\Support\BusinessTime::local($item->starts_at)->format('M j, g:i a') }} → {{ \App\Support\BusinessTime::local($item->ends_at)->format('M j, g:i a T') }}</span><span>{{ $item->show_banner ? 'Banner on' : 'Banner off' }}{{ $item->email_requested ? ' · Email requested (paused)' : '' }}{{ $item->push_requested ? ' · Push requested (paused)' : '' }}</span></div>
                    <div class="vxc-actions"><x-filament::button color="gray" size="sm" wire:click="editAnnouncement({{ $item->id }})">Edit draft</x-filament::button>@if ($item->status !== 'published')<x-filament::button size="sm" wire:click="publishPreview({{ $item->id }})" wire:loading.attr="disabled">Activate owner preview</x-filament::button>@else<x-filament::button color="gray" size="sm" wire:click="archiveAnnouncement({{ $item->id }})" wire:loading.attr="disabled">Stop preview</x-filament::button>@endif</div>
                </article>
            @empty
                <div class="vxc-empty"><x-filament::icon icon="heroicon-o-megaphone" /><h3>Your team's noticeboard</h3><p>Try a scheduled announcement for everyone, a role, or specific people.</p></div>
            @endforelse
            @error('announcement')<p class="vxc-error" role="alert">{{ $message }}</p>@enderror
            @if ($announcements->hasPages())<div class="vxc-pages"><button type="button" wire:click="paginateSection('announcementPage', {{ max(1, $announcementPage - 1) }})" @disabled($announcements->onFirstPage())>Previous</button><span>{{ $announcements->currentPage() }} / {{ $announcements->lastPage() }}</span><button type="button" wire:click="paginateSection('announcementPage', {{ $announcementPage + 1 }})" @disabled(! $announcements->hasMorePages())>Next</button></div>@endif
        </section>
    @endif

    <x-filament::modal id="communication-new-chat" width="2xl">
        <x-slot name="heading">New preview conversation</x-slot><x-slot name="description">Choose participants to test the flow. They cannot see or receive anything during owner preview.</x-slot>
        <form wire:submit="startChat" class="vxc vxc-form">
            <label>Conversation type<select wire:model.live="kind"><option value="direct">Direct message</option><option value="group">Group chat</option></select></label>
            @if ($kind === 'group')<label>Group name<input wire:model="groupTitle" maxlength="120" placeholder="e.g. Stream team" required></label>@endif
            <label>Find people<input wire:model.live.debounce.300ms="peopleSearch" type="search" placeholder="Search by name"></label>
            <fieldset class="vxc-people"><legend>{{ $kind === 'direct' ? 'Choose one person' : 'Choose group members' }}</legend>@foreach ($people as $person)@if ($person->id !== auth()->id())<label wire:key="chat-person-{{ $person->id }}"><input type="{{ $kind === 'direct' ? 'radio' : 'checkbox' }}" wire:model="{{ $kind === 'direct' ? 'directMember' : 'members' }}" value="{{ $person->id }}" @if ($kind === 'direct') name="chat-recipient" @endif>{{ $person->name }}</label>@endif @endforeach</fieldset>
            @if ($errors->any())<div class="vxc-error" role="alert">{{ $errors->first() }}</div>@endif
            <div class="vxc-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="startChat">Open preview chat</x-filament::button><x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'communication-new-chat'})">Cancel</x-filament::button></div>
        </form>
    </x-filament::modal>
    <x-filament::modal id="communication-announcement" width="3xl">
        <x-slot name="heading">{{ $announcementId ? 'Edit announcement draft' : 'New announcement' }}</x-slot><x-slot name="description">Saved as an owner-only draft. Activating a preview never delivers to the selected audience.</x-slot>
        <form wire:submit="saveAnnouncement" class="vxc vxc-form">
            <label>Title<input wire:model="announcement.title" maxlength="160" required placeholder="e.g. Weekly team update"></label>
            <label>Announcement<textarea wire:model="announcement.body" rows="4" maxlength="10000" required placeholder="What should your team know?"></textarea></label>
            <div class="vxc-form-grid"><label>Audience<select wire:model.live="announcement.audience"><option value="all">Everyone</option><option value="roles">Certain roles</option><option value="users">Specific people</option></select></label><label>Style<select wire:model="announcement.tone"><option value="info">Information</option><option value="success">Good news</option><option value="warning">Important</option><option value="danger">Urgent</option></select></label></div>
            @if (($announcement['audience'] ?? '') === 'roles')<fieldset class="vxc-people"><legend>Roles</legend>@foreach ($roles as $role)<label wire:key="audience-role-{{ $role->id }}"><input type="checkbox" wire:model="announcement.role_ids" value="{{ $role->id }}">{{ str_replace('_', ' ', $role->name) }}</label>@endforeach</fieldset>@endif
            @if (($announcement['audience'] ?? '') === 'users')<label>Find people<input wire:model.live.debounce.300ms="peopleSearch" type="search" placeholder="Search by name"></label><fieldset class="vxc-people"><legend>People</legend>@foreach ($people as $person)<label wire:key="audience-user-{{ $person->id }}"><input type="checkbox" wire:model="announcement.user_ids" value="{{ $person->id }}">{{ $person->name }}</label>@endforeach</fieldset>@endif
            <div class="vxc-form-grid"><label>Starts ({{ \App\Support\BusinessTime::zone() }})<input type="datetime-local" wire:model="announcement.starts_at" required></label><label>Ends ({{ \App\Support\BusinessTime::zone() }})<input type="datetime-local" wire:model="announcement.ends_at" required></label></div>
            <fieldset class="vxc-channels"><legend>Placement & delivery</legend><label><input type="checkbox" wire:model="announcement.show_banner">Show as an on-screen banner</label><label><input type="checkbox" wire:model="announcement.email_requested">Request email delivery when launched</label><label><input type="checkbox" wire:model="announcement.push_requested">Request PWA push when launched</label><small>Email and push are paused. Preview records stay separate from the future live rollout.</small></fieldset>
            @if ($errors->any())<div class="vxc-error" role="alert">{{ $errors->first() }}</div>@endif
            <div class="vxc-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveAnnouncement">Save draft</x-filament::button><x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'communication-announcement'})">Cancel</x-filament::button></div>
        </form>
    </x-filament::modal>
</div>
</x-filament-panels::page>
