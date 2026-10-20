<?php

namespace App\Filament\Pages;

use App\Models\TeamAnnouncement;
use App\Models\User;
use App\Services\TeamCommunications;
use App\Support\BusinessTime;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Spatie\Permission\Models\Role;
use Livewire\WithFileUploads;

class Communications extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Team communications';
    protected static ?string $slug = 'communications';
    protected static bool $shouldRegisterNavigation = false;

    public string $tab = 'messages';
    #[Locked] public ?int $conversationId = null;
    #[Locked] public ?int $announcementId = null;
    #[Locked] public string $clientKey = '';
    public string $body = '';
    public array $attachments = [];
    public string $inboxSearch = '';
    public string $inboxFilter = 'all';
    public string $messageSearch = '';
    public string $announcementSearch = '';
    public string $announcementFilter = 'all';
    public string $kind = 'direct';
    public string $groupTitle = '';
    public array $members = [];
    public string $directMember = '';
    public string $peopleSearch = '';
    public int $threadPage = 1;
    public int $messagePage = 1;
    public int $announcementPage = 1;
    public array $announcement = [];

    public static function canAccess(): bool { return TeamCommunications::canAccess(auth()->user()); }
    public function getSubheading(): ?string { return 'Conversations and updates, all in one place.'; }
    public function getView(): string { return 'filament.pages.communications'; }
    private function actor(): User
    {
        abort_unless(static::canAccess(), 403);
        return auth()->user();
    }
    public function mount(): void { $this->actor(); $this->clientKey = (string) Str::uuid(); $this->resetAnnouncement(); }
    public function hydrate(): void { $this->actor(); }
    public function changeTab(string $tab): void
    {
        $this->actor();
        $this->tab = in_array($tab, ['messages', 'announcements'], true) ? $tab : 'messages';
        $this->resetValidation();
    }
    public function startChat(): void
    {
        $thread = app(TeamCommunications::class)->start($this->actor(), ['kind' => $this->kind, 'title' => trim($this->groupTitle), 'members' => $this->kind === 'direct' ? [$this->directMember] : $this->members]);
        $this->selectConversation($thread->id);
        $this->reset('members', 'directMember', 'groupTitle', 'peopleSearch');
        $this->threadPage = 1;
        $this->dispatch('close-modal', id: 'communication-new-chat');
        Notification::make()->title('Preview conversation opened')->body('Only your owner account can access this conversation during development.')->success()->send();
    }
    public function selectConversation(int $id): void
    {
        app(TeamCommunications::class)->conversation($this->actor(), $id);
        $this->conversationId = $id;
        $this->body = '';
        $this->attachments = [];
        $this->messageSearch = '';
        $this->messagePage = 1;
        $this->clientKey = (string) Str::uuid();
        $this->resetValidation();
    }
    public function sendMessage(): void
    {
        $actor = $this->actor();
        abort_unless($this->conversationId, 404);
        app(TeamCommunications::class)->send($actor, $this->conversationId, $this->body, $this->clientKey, $this->attachments);
        $this->body = '';
        $this->attachments = [];
        $this->clientKey = (string) Str::uuid();
        $this->messagePage = 1;
        $this->resetValidation();
        $this->dispatch('communication-message-saved');
    }
    public function resetAnnouncement(): void
    {
        $this->actor();
        $this->announcementId = null;
        $this->announcement = ['title' => '', 'body' => '', 'tone' => 'info', 'audience' => 'all', 'role_ids' => [], 'user_ids' => [], 'show_banner' => true, 'in_app_requested' => true, 'dismissible' => true, 'email_requested' => false, 'push_requested' => false,
            'starts_at' => BusinessTime::local()->format('Y-m-d\TH:i'), 'ends_at' => BusinessTime::local()->addDay()->format('Y-m-d\TH:i')];
        $this->resetValidation();
    }
    public function newAnnouncement(): void
    {
        $this->resetAnnouncement();
        $this->dispatch('open-modal', id: 'communication-announcement');
    }
    public function editAnnouncement(int $id): void
    {
        $this->actor();
        $record = TeamAnnouncement::where('is_preview', true)->findOrFail($id);
        $this->announcementId = $record->id;
        $this->announcement = $record->only(['title', 'body', 'tone', 'audience', 'role_ids', 'user_ids', 'show_banner', 'in_app_requested', 'dismissible', 'email_requested', 'push_requested']);
        foreach (['starts_at', 'ends_at'] as $field) $this->announcement[$field] = BusinessTime::local($record->$field)->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'communication-announcement');
    }
    public function saveAnnouncement(bool $activate = false): void
    {
        $record = app(TeamCommunications::class)->saveAnnouncement($this->actor(), $this->announcement, $this->announcementId);
        if ($activate) {
            app(TeamCommunications::class)->publishPreview($this->actor(), $record->id);
            $this->dispatch('announcement-preview-changed');
        }
        $this->resetAnnouncement();
        $this->announcementPage = 1;
        $this->dispatch('close-modal', id: 'communication-announcement');
        Notification::make()->title($activate ? 'Owner preview activated' : 'Announcement draft saved')->body('Nothing was delivered to other accounts.')->success()->send();
    }
    public function publishPreview(int $id): void
    {
        app(TeamCommunications::class)->publishPreview($this->actor(), $id);
        Notification::make()->title('Owner preview activated')->body('The banner follows its schedule and stays visible only to you.')->success()->send();
        $this->dispatch('announcement-preview-changed');
    }
    public function archiveAnnouncement(int $id): void
    {
        app(TeamCommunications::class)->archive($this->actor(), $id);
        Notification::make()->title('Preview stopped')->success()->send();
        $this->dispatch('announcement-preview-changed');
    }
    public function paginateSection(string $section, int $page): void
    {
        $this->actor();
        if (in_array($section, ['threadPage', 'messagePage', 'announcementPage'], true)) $this->$section = max(1, $page);
    }
    public function updated(string $property): void
    {
        $this->actor();
        if (in_array($property, ['inboxSearch', 'inboxFilter'], true)) $this->threadPage = 1;
        if ($property === 'messageSearch') $this->messagePage = 1;
        if (in_array($property, ['announcementSearch', 'announcementFilter'], true)) $this->announcementPage = 1;
    }
    public function removeAttachment(int $index): void
    {
        $this->actor();
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
    public function updatedAttachments(): void
    {
        $this->actor();
        $this->validate(['attachments' => 'array|max:3', 'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,webp,txt,csv|extensions:pdf,png,jpg,jpeg,webp,txt,csv']);
    }
    protected function getViewData(): array
    {
        $actor = $this->actor();
        $service = app(TeamCommunications::class);
        $selected = $this->conversationId ? $service->conversation($actor, $this->conversationId) : null;
        $messageSearch = mb_substr(trim($this->messageSearch), 0, 100);
        $messages = $selected?->messages()->with(['sender:id,name', 'attachments'])->when($messageSearch !== '', fn ($q) => $q->where('body', 'like', '%'.$messageSearch.'%'))
            ->latest('id')->paginate(40, ['*'], 'messagePage', max(1, $this->messagePage));
        if ($selected && $messages?->count() && $this->messagePage === 1 && $messageSearch === '') {
            $selected->members()->updateExistingPivot($actor->id, ['last_read_message_id' => $messages->getCollection()->max('id')]);
        }
        $threads = $service->inbox($actor, $this->inboxSearch, $this->inboxFilter)->orderByDesc('last_message_at')->orderByDesc('id')->paginate(12, ['*'], 'threadPage', max(1, $this->threadPage));
        $unreadTotal = $service->inbox($actor, '', 'unread')->count();
        $announcementSearch = mb_substr(trim($this->announcementSearch), 0, 100);
        $announcements = TeamAnnouncement::where('is_preview', true)
            ->when($announcementSearch !== '', fn ($q) => $q->where(fn ($s) => $s->where('title', 'like', '%'.$announcementSearch.'%')->orWhere('body', 'like', '%'.$announcementSearch.'%')))
            ->when($this->announcementFilter === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($this->announcementFilter === 'scheduled', fn ($q) => $q->where('status', 'published')->where('starts_at', '>', now())->where('ends_at', '>', now()))
            ->when($this->announcementFilter === 'active', fn ($q) => $q->where('status', 'published')->where('starts_at', '<=', now())->where('ends_at', '>', now()))
            ->latest('id')->paginate(10, ['*'], 'announcementPage', max(1, $this->announcementPage));
        $audienceCounts = $announcements->getCollection()->mapWithKeys(fn ($a) => [$a->id => $service->recipientCount($actor, $a)]);
        $search = mb_substr($this->peopleSearch, 0, 100);
        $people = User::select('id', 'name')->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))->orderBy('name')->limit(60)->get();
        $chosenPeople = User::select('id', 'name')->whereIn('id', array_slice(array_unique(array_merge($this->members, [$this->directMember], $this->announcement['user_ids'] ?? [])), 0, 500))->get();
        $people = $people->merge($chosenPeople)->unique('id')->sortBy('name');
        return compact('threads', 'selected', 'messages', 'announcements', 'audienceCounts', 'people', 'unreadTotal') + ['roles' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])];
    }
}
