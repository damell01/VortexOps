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

class Communications extends Page
{
    protected static ?string $title = 'Messages & Announcements';
    protected static ?string $slug = 'communications';
    protected static bool $shouldRegisterNavigation = false;

    public string $tab = 'messages';
    #[Locked] public ?int $conversationId = null;
    #[Locked] public ?int $announcementId = null;
    #[Locked] public string $clientKey = '';
    public string $body = '';
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
        $this->messagePage = 1;
        $this->clientKey = (string) Str::uuid();
        $this->resetValidation();
    }
    public function sendMessage(): void
    {
        $actor = $this->actor();
        abort_unless($this->conversationId, 404);
        app(TeamCommunications::class)->send($actor, $this->conversationId, $this->body, $this->clientKey);
        $this->body = '';
        $this->clientKey = (string) Str::uuid();
        $this->messagePage = 1;
        $this->resetValidation();
        $this->dispatch('communication-message-saved');
    }
    public function resetAnnouncement(): void
    {
        $this->actor();
        $this->announcementId = null;
        $this->announcement = ['title' => '', 'body' => '', 'tone' => 'info', 'audience' => 'all', 'role_ids' => [], 'user_ids' => [], 'show_banner' => true, 'email_requested' => false, 'push_requested' => false,
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
        $this->announcement = $record->only(['title', 'body', 'tone', 'audience', 'role_ids', 'user_ids', 'show_banner', 'email_requested', 'push_requested']);
        foreach (['starts_at', 'ends_at'] as $field) $this->announcement[$field] = BusinessTime::local($record->$field)->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->dispatch('open-modal', id: 'communication-announcement');
    }
    public function saveAnnouncement(): void
    {
        app(TeamCommunications::class)->saveAnnouncement($this->actor(), $this->announcement, $this->announcementId);
        $this->resetAnnouncement();
        $this->announcementPage = 1;
        $this->dispatch('close-modal', id: 'communication-announcement');
        Notification::make()->title('Announcement draft saved')->body('Nothing was delivered to other accounts.')->success()->send();
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
    protected function getViewData(): array
    {
        $actor = $this->actor();
        $service = app(TeamCommunications::class);
        $threads = $service->conversations($actor)->with('members:id,name')->withCount('messages')->orderByDesc('last_message_at')->orderByDesc('id')->paginate(12, ['*'], 'threadPage', max(1, $this->threadPage));
        $selected = $this->conversationId ? $service->conversation($actor, $this->conversationId) : null;
        $messages = $selected?->messages()->with('sender:id,name')->latest('id')->paginate(40, ['*'], 'messagePage', max(1, $this->messagePage));
        if ($selected && $messages?->count() && $this->messagePage === 1) {
            $selected->members()->updateExistingPivot($actor->id, ['last_read_message_id' => $messages->getCollection()->max('id')]);
        }
        $announcements = TeamAnnouncement::where('is_preview', true)->latest('id')->paginate(10, ['*'], 'announcementPage', max(1, $this->announcementPage));
        $audienceCounts = $announcements->getCollection()->mapWithKeys(fn ($a) => [$a->id => $service->recipientCount($actor, $a)]);
        $search = mb_substr($this->peopleSearch, 0, 100);
        $people = User::select('id', 'name')->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))->orderBy('name')->limit(60)->get();
        $chosenPeople = User::select('id', 'name')->whereIn('id', array_slice(array_unique(array_merge($this->members, [$this->directMember], $this->announcement['user_ids'] ?? [])), 0, 500))->get();
        $people = $people->merge($chosenPeople)->unique('id')->sortBy('name');
        return compact('threads', 'selected', 'messages', 'announcements', 'audienceCounts', 'people') + ['roles' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])];
    }
}
