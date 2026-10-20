<x-filament-panels::page>
<div class="vxc vxc-redesign" data-vx-page="communications">
    <div class="vxc-preview"><span><x-filament::icon icon="heroicon-o-lock-closed" /><strong>Owner preview</strong></span><p>Only you can see this workspace. Recipient notifications, email and push are paused.</p></div>
    <div class="vxc-workspace">
    <div class="vxc-tabs" role="group" aria-label="Communication workspace">
        <button type="button" wire:click="changeTab('messages')" aria-pressed="{{ $tab === 'messages' ? 'true' : 'false' }}"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" />Messages @if ($unreadTotal)<span class="vxc-unread">{{ $unreadTotal }}</span>@endif</button>
        <button type="button" wire:click="changeTab('announcements')" aria-pressed="{{ $tab === 'announcements' ? 'true' : 'false' }}"><x-filament::icon icon="heroicon-o-megaphone" />Announcements</button>
    </div>
    @if ($tab === 'messages')
        <div class="vxc-inbox" wire:poll.visible.15s>
            <section class="vxc-threads" aria-label="Conversations">
                <div class="vxc-section-head"><h2>Inbox @if ($unreadTotal)<span class="vxc-unread">{{ $unreadTotal }}</span>@endif</h2><x-filament::button size="sm" icon="heroicon-o-pencil-square" x-on:click="$dispatch('open-modal', {id: 'communication-new-chat'})" aria-label="New conversation">New chat</x-filament::button></div>
                <div class="vxc-inbox-tools"><label class="vxc-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.300ms="inboxSearch" placeholder="Search conversations…" aria-label="Search conversations"></label><div class="vxc-filter-pills" role="group" aria-label="Inbox filter">@foreach (['all' => 'All', 'unread' => 'Unread', 'groups' => 'Groups'] as $key => $label)<button type="button" wire:click="$set('inboxFilter', '{{ $key }}')" aria-pressed="{{ $inboxFilter === $key ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div></div>
                @forelse ($threads as $thread)
                    <button type="button" wire:click="selectConversation({{ $thread->id }})" class="vxc-thread {{ $conversationId === $thread->id ? 'is-selected' : '' }}" @if ($conversationId === $thread->id) aria-current="true" @endif wire:key="thread-{{ $thread->id }}">
                        <span class="vxc-avatar">@if ($thread->kind === 'group')<x-filament::icon icon="heroicon-o-user-group" />@else{{ mb_strtoupper(mb_substr($thread->labelFor(auth()->user()), 0, 2)) }}@endif</span><span class="vxc-thread-copy"><span><b>{{ $thread->labelFor(auth()->user()) }}</b><time>{{ $thread->last_message_at ? \App\Support\BusinessTime::local($thread->last_message_at)->format('g:i a') : '' }}</time></span><span><small>{{ \Illuminate\Support\Str::limit($thread->latestMessage?->body ?: ($thread->messages_count ? 'Attachment' : 'Start the conversation'), 70) }}</small>@if ($thread->unread_count)<span class="vxc-unread" aria-label="{{ $thread->unread_count }} unread messages">{{ $thread->unread_count }}</span>@endif</span></span>
                    </button>
                @empty
                    <div class="vxc-empty"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /><h3>{{ $inboxSearch || $inboxFilter !== 'all' ? 'No matching conversations' : 'Your team inbox' }}</h3><p>Start a direct chat or create a group to try the owner preview.</p></div>
                @endforelse
                @if ($threads->hasPages())<div class="vxc-pages"><button type="button" wire:click="paginateSection('threadPage', {{ max(1, $threadPage - 1) }})" @disabled($threads->onFirstPage())>Previous</button><span>{{ $threads->currentPage() }} / {{ $threads->lastPage() }}</span><button type="button" wire:click="paginateSection('threadPage', {{ $threadPage + 1 }})" @disabled(! $threads->hasMorePages())>Next</button></div>@endif
            </section>
            <section class="vxc-conversation" aria-label="Message thread">
                @if ($selected)
                    <div class="vxc-section-head vxc-chat-head"><span class="vxc-avatar">{{ mb_strtoupper(mb_substr($selected->labelFor(auth()->user()), 0, 2)) }}</span><div><h2>{{ $selected->labelFor(auth()->user()) }}</h2><p>{{ $selected->kind === 'group' ? $selected->members->count().' members · '.$selected->members->pluck('name')->implode(', ') : 'Direct message' }}</p></div><span class="vxc-badge">Owner preview</span></div>
                    <label class="vxc-search vxc-thread-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.300ms="messageSearch" placeholder="Search this conversation…" aria-label="Search this conversation"></label>
                    <div class="vxc-history" aria-label="Messages" wire:key="history-{{ $conversationId }}-{{ $messagePage }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" x-on:communication-message-saved.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                        @if ($messages->hasPages())<div class="vxc-pages"><button type="button" wire:click="paginateSection('messagePage', {{ $messagePage + 1 }})" @disabled(! $messages->hasMorePages())>Older messages</button><span>{{ $messages->total() }} total</span><button type="button" wire:click="paginateSection('messagePage', {{ max(1, $messagePage - 1) }})" @disabled($messages->onFirstPage())>Newer</button></div>@endif
                        @php $lastDate = null; @endphp
                        @forelse ($messages->getCollection()->reverse() as $message)
                            @php $day = \App\Support\BusinessTime::local($message->created_at)->format('M j, Y'); @endphp
                            @if ($day !== $lastDate)<div class="vxc-date-divider"><span>{{ $day }}</span></div>@php $lastDate = $day; @endphp @endif
                            <article class="vxc-message-wrap {{ $message->sender_id === auth()->id() ? 'is-own' : '' }}" wire:key="message-{{ $message->id }}"><span class="vxc-avatar">{{ mb_strtoupper(mb_substr($message->sender?->name ?? '?', 0, 1)) }}</span><div class="vxc-message-stack"><div class="vxc-message"><b>{{ $message->sender?->name ?? 'Deleted account' }}</b>@if ($message->body !== '')<p>{{ $message->body }}</p>@endif
                                @foreach ($message->attachments as $file)<a class="vxc-file" href="{{ route('communications.attachment', $file->id) }}"><x-filament::icon icon="heroicon-o-document-arrow-down" /><span><strong>{{ $file->name }}</strong><small>{{ $file->size >= 1048576 ? number_format($file->size / 1048576, 1).' MB' : number_format($file->size / 1024, 0).' KB' }} · Download</small></span></a>@endforeach
                            </div><time datetime="{{ $message->created_at->toIso8601String() }}">{{ \App\Support\BusinessTime::local($message->created_at)->format('g:i a') }}</time></div></article>
                        @empty
                            <div class="vxc-empty"><h3>{{ $messageSearch ? 'No matching messages' : 'Start the conversation' }}</h3><p>Messages stay private to your owner account during this preview.</p></div>
                        @endforelse
                    </div>
                    <form wire:submit="sendMessage" class="vxc-composer">
                        <div class="vxc-compose-row"><label class="vxc-attach" title="Attach up to 3 files"><input type="file" wire:model="attachments" multiple accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv" class="sr-only" aria-label="Attach files"><x-filament::icon icon="heroicon-o-paper-clip" /></label><label for="vxc-message-body" class="sr-only">Write a message</label><textarea id="vxc-message-body" wire:model="body" rows="1" maxlength="5000" placeholder="Write a message…"></textarea><x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendMessage,attachments">Send preview</x-filament::button></div>
                        @if ($attachments)<div class="vxc-pending-files">@foreach ($attachments as $index => $file)<span>{{ $file->getClientOriginalName() }}<button type="button" wire:click="removeAttachment({{ $index }})" aria-label="Remove {{ $file->getClientOriginalName() }}">×</button></span>@endforeach</div>@endif
                        <div wire:loading wire:target="attachments" role="status" class="vxc-uploading">Uploading attachments…</div>
                        @if ($errors->any())<p class="vxc-error" role="alert">{{ $errors->first() }}</p>@endif
                        <small class="vxc-compose-note"><x-filament::icon icon="heroicon-o-lock-closed" />Preview only · Visible to your owner account · Up to 3 files, 10 MB each</small>
                    </form>
                @else
                    <div class="vxc-empty vxc-start"><span class="vxc-empty-orb"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /></span><h3>Your conversations, together</h3><p>Choose a conversation or start a new one. Direct messages and team groups live here.</p><x-filament::button icon="heroicon-o-plus" x-on:click="$dispatch('open-modal', {id: 'communication-new-chat'})">New conversation</x-filament::button></div>
                @endif
            </section>
        </div>
    @else
        <section class="vxc-announcements" aria-label="Announcements">
            <div class="vxc-section-head"><div><h2>Team announcements</h2><p>Keep your team informed with important updates.</p></div><x-filament::button icon="heroicon-o-plus" wire:click="newAnnouncement">New announcement</x-filament::button></div>
            <div class="vxc-notice-tools"><div class="vxc-filter-pills" role="group" aria-label="Announcement filter">@foreach (['all' => 'All', 'draft' => 'Drafts', 'scheduled' => 'Scheduled', 'active' => 'Active'] as $key => $label)<button type="button" wire:click="$set('announcementFilter', '{{ $key }}')" aria-pressed="{{ $announcementFilter === $key ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div><label class="vxc-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input type="search" wire:model.live.debounce.300ms="announcementSearch" placeholder="Search announcements…" aria-label="Search announcements"></label></div>
            <div class="vxc-notice-columns"><span>Announcement</span><span>Audience & schedule</span><span>Status</span><span>Actions</span></div>
            @forelse ($announcements as $item)
                <article class="vxc-announcement" wire:key="announcement-{{ $item->id }}"><div class="vxc-notice-title"><span class="vxc-avatar"><x-filament::icon icon="heroicon-o-megaphone" /></span><div><h3>{{ $item->title }}</h3><p>{{ \Illuminate\Support\Str::limit($item->body, 110) }}</p></div></div><div class="vxc-announcement-meta"><span>{{ $item->audience === 'all' ? 'Everyone' : ($item->audience === 'roles' ? 'Selected roles' : 'Selected people') }} · {{ $audienceCounts[$item->id] }} intended recipients</span><span>{{ \App\Support\BusinessTime::local($item->starts_at)->format('M j, g:i a') }} → {{ \App\Support\BusinessTime::local($item->ends_at)->format('M j, g:i a T') }}</span></div><span class="vxc-badge vxc-status-{{ $item->status }}">{{ $item->phase() }}</span><div class="vxc-actions"><button type="button" wire:click="editAnnouncement({{ $item->id }})" class="vxc-text-button">Edit</button>@if ($item->status !== 'published')<button type="button" wire:click="publishPreview({{ $item->id }})" wire:loading.attr="disabled" class="vxc-text-button">Preview</button>@else<button type="button" wire:click="archiveAnnouncement({{ $item->id }})" wire:loading.attr="disabled" class="vxc-text-button">Stop</button>@endif</div></article>
            @empty
                <div class="vxc-empty"><x-filament::icon icon="heroicon-o-megaphone" /><h3>No matching announcements</h3><p>Create a draft, choose who it is for, and preview it on screen.</p></div>
            @endforelse
            @error('announcement')<p class="vxc-error" role="alert">{{ $message }}</p>@enderror
            @if ($announcements->hasPages())<div class="vxc-pages"><button type="button" wire:click="paginateSection('announcementPage', {{ max(1, $announcementPage - 1) }})" @disabled($announcements->onFirstPage())>Previous</button><span>{{ $announcements->currentPage() }} / {{ $announcements->lastPage() }}</span><button type="button" wire:click="paginateSection('announcementPage', {{ $announcementPage + 1 }})" @disabled(! $announcements->hasMorePages())>Next</button></div>@endif
        </section>
    @endif
    </div>
    <x-filament::modal id="communication-new-chat" width="sm">
        <x-slot name="heading">New conversation</x-slot><x-slot name="description">Private owner preview. Selected people will not be notified.</x-slot>
        <form wire:submit="startChat" class="vxc vxc-form vxc-picker">
            <div class="vxc-segments" role="group" aria-label="Conversation type">@foreach (['direct' => 'Direct message', 'group' => 'Group chat'] as $key => $label)<button type="button" wire:click="$set('kind', '{{ $key }}')" aria-pressed="{{ $kind === $key ? 'true' : 'false' }}">{{ $label }}</button>@endforeach</div>
            @if ($kind === 'group')<label>Group name<input wire:model="groupTitle" maxlength="120" placeholder="e.g. Stream team" required></label>@endif
            <label class="vxc-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input wire:model.live.debounce.300ms="peopleSearch" type="search" placeholder="Search people…" aria-label="Find people"></label>
            <div class="vxc-selected-people">@foreach ($people as $person)@if (($kind === 'direct' && (string) $person->id === $directMember) || ($kind === 'group' && in_array($person->id, $members)))<span>{{ $person->name }}</span>@endif @endforeach</div>
            <fieldset class="vxc-people"><legend class="sr-only">{{ $kind === 'direct' ? 'Choose one person' : 'Choose group members' }}</legend>@foreach ($people as $person)@if ($person->id !== auth()->id())<label wire:key="chat-person-{{ $person->id }}"><input type="{{ $kind === 'direct' ? 'radio' : 'checkbox' }}" wire:model.live="{{ $kind === 'direct' ? 'directMember' : 'members' }}" value="{{ $person->id }}" @if ($kind === 'direct') name="chat-recipient" @endif><span class="vxc-avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>{{ $person->name }}</label>@endif @endforeach</fieldset>
            @if ($errors->any())<div class="vxc-error" role="alert">{{ $errors->first() }}</div>@endif
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="startChat">Create preview</x-filament::button>
        </form>
    </x-filament::modal>
    <x-filament::modal id="communication-announcement" width="6xl" sticky-header sticky-footer>
        <x-slot name="heading">{{ $announcementId ? 'Edit announcement' : 'Create announcement' }}</x-slot><x-slot name="description">Choose who sees it and when.</x-slot>
        <form wire:submit="saveAnnouncement" class="vxc vxc-form vxc-announcement-editor" x-data="{ draft: $wire.entangle('announcement') }">
            <div class="vxc-editor-main">
                <label>Title<input x-model="draft.title" maxlength="160" required placeholder="Announcement title"></label>
                <label>Message<textarea x-model="draft.body" rows="5" maxlength="10000" required placeholder="What should your team know?"></textarea></label>
                <fieldset><legend>Audience</legend><div class="vxc-segments">@foreach (['all' => 'Everyone', 'roles' => 'Roles', 'users' => 'Specific people'] as $key => $label)<button type="button" wire:click="$set('announcement.audience', '{{ $key }}')" :aria-pressed="draft.audience === '{{ $key }}'">{{ $label }}</button>@endforeach</div></fieldset>
                @if (($announcement['audience'] ?? '') === 'roles')<fieldset class="vxc-people vxc-role-picker"><legend class="sr-only">Choose roles</legend>@foreach ($roles as $role)<label wire:key="audience-role-{{ $role->id }}"><input type="checkbox" x-model="draft.role_ids" value="{{ $role->id }}">{{ ucwords(str_replace('_', ' ', $role->name)) }}</label>@endforeach</fieldset>@endif
                @if (($announcement['audience'] ?? '') === 'users')<label class="vxc-search"><x-filament::icon icon="heroicon-o-magnifying-glass" /><input wire:model.live.debounce.300ms="peopleSearch" type="search" placeholder="Search people…" aria-label="Find announcement recipients"></label><fieldset class="vxc-people"><legend class="sr-only">Choose people</legend>@foreach ($people as $person)<label wire:key="audience-user-{{ $person->id }}"><input type="checkbox" x-model="draft.user_ids" value="{{ $person->id }}">{{ $person->name }}</label>@endforeach</fieldset>@endif
                <fieldset><legend>Style</legend><div class="vxc-style-options">@foreach (['info' => 'Info', 'success' => 'Success', 'warning' => 'Important', 'danger' => 'Urgent'] as $key => $label)<label :class="draft.tone === '{{ $key }}' ? 'is-selected' : ''"><input type="radio" x-model="draft.tone" value="{{ $key }}" name="announcement-style"><span class="vxc-tone-dot vxc-dot-{{ $key }}"></span>{{ $label }}</label>@endforeach</div></fieldset>
                <fieldset><legend>Schedule</legend><div class="vxc-form-grid"><label>Starts ({{ \App\Support\BusinessTime::zone() }})<input type="datetime-local" x-model="draft.starts_at" required></label><label>Ends ({{ \App\Support\BusinessTime::zone() }})<input type="datetime-local" x-model="draft.ends_at" required></label></div></fieldset>
                @if ($errors->any())<div class="vxc-error" role="alert">{{ $errors->first() }}</div>@endif
            </div>
            <aside class="vxc-editor-preview">
                <span class="vxc-preview-lock"><x-filament::icon icon="heroicon-o-eye" />Only you can see this preview.</span>
                <div class="vxc-live-preview"><h3>Banner preview</h3><div class="vxc-preview-nav"><img src="/img/pokeball.svg" alt="" width="24" height="24"><b>Vortex Ops</b><span>Dashboard &nbsp; Shows &nbsp; Inventory</span></div><div class="vxc-banner" :class="'vxc-tone-'+draft.tone"><x-filament::icon icon="heroicon-o-megaphone" /><div><strong x-text="draft.title || 'Your announcement title'"></strong><p x-text="draft.body || 'Your message will appear here as you type.'"></p></div><x-filament::icon x-show="draft.dismissible" icon="heroicon-o-x-mark" /></div><small x-show="!draft.show_banner">Banner placement is off. This is a design preview.</small></div>
                <fieldset class="vxc-delivery"><legend>Placement & delivery</legend>
                    @foreach (['show_banner' => ['heroicon-o-computer-desktop', 'Dashboard banner', 'Show an on-screen banner'], 'in_app_requested' => ['heroicon-o-bell', 'In-app notification', 'Delivery paused during preview'], 'email_requested' => ['heroicon-o-envelope', 'Email', 'Delivery paused during preview'], 'push_requested' => ['heroicon-o-device-phone-mobile', 'PWA push', 'Delivery paused during preview'], 'dismissible' => ['heroicon-o-cog-6-tooth', 'Allow dismissal', 'Let users dismiss the banner']] as $key => [$icon, $label, $help])
                        <label><x-filament::icon :icon="$icon" /><span><b>{{ $label }}</b><small>{{ $help }}</small></span><input type="checkbox" x-model="draft.{{ $key }}" role="switch" aria-label="{{ $label }}"></label>
                    @endforeach
                </fieldset>
                <div class="vxc-audience-summary"><x-filament::icon icon="heroicon-o-user-group" /><div><strong x-text="'Audience: '+(draft.audience === 'all' ? 'Everyone' : draft.audience === 'roles' ? draft.role_ids.length+' selected roles' : draft.user_ids.length+' selected people')"></strong><small x-text="'Visible until '+(draft.ends_at || 'an end time is selected').replace('T', ' ')+' ({{ \App\Support\BusinessTime::zone() }})'"></small></div></div>
            </aside>
            <div class="vxc-editor-footer"><small><x-filament::icon icon="heroicon-o-lock-closed" />Saved as an owner-only draft</small><div class="vxc-actions"><x-filament::button type="submit" color="gray" wire:loading.attr="disabled" wire:target="saveAnnouncement">Save draft</x-filament::button><x-filament::button type="button" wire:click="saveAnnouncement(true)" wire:loading.attr="disabled" wire:target="saveAnnouncement">Preview announcement</x-filament::button></div></div>
        </form>
    </x-filament::modal>
</div>
</x-filament-panels::page>
