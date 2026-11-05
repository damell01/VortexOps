<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\TeamCommunications;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuickMessages extends Component
{
    use WithFileUploads;

    public bool $creating = false;
    public array $selectedRoles = [];
    public string $groupTitle = '';
    public array $members = [];
    public array $attachments = [];
    public string $peopleSearch = '';

    public bool $open = false;
    #[Locked]
    public ?int $conversationId = null;
    public string $body = '';
    #[Locked]
    public string $clientKey = '';

    public function mount(): void
    {
        $this->authorizeOwner();
        $this->clientKey = (string) Str::uuid();
    }

    private function authorizeOwner(): User
    {
        abort_unless(TeamCommunications::canAccess(auth()->user()), 403);

        return auth()->user();
    }

    public function openConversation(int $id): void
    {
        app(TeamCommunications::class)->conversation($this->authorizeOwner(), $id);
        $this->conversationId = $id;
        $this->body = '';
        $this->clientKey = (string) Str::uuid();
        $this->open = true;
        $this->resetValidation();
    }

    public function back(): void
    {
        $this->authorizeOwner();
        $this->conversationId = null;
        $this->body = '';
    }

    public function updatedSelectedRoles(): void
    {
        $this->authorizeOwner();
        $this->selectedRoles = array_values(array_unique(array_filter(array_map('strval', $this->selectedRoles))));
        $roleIds = \Spatie\Permission\Models\Role::query()->where('guard_name', 'web')->whereIn('name', $this->selectedRoles)->pluck('id');
        $ids = User::query()->whereKeyNot(auth()->id())->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))->pluck('id')->all();
        $this->members = array_values(array_unique(array_merge(array_map('intval', $this->members), $ids)));
    }

    public function updatedMembers(): void
    {
        $this->authorizeOwner();
        $this->members = array_values(array_unique(array_map('intval', $this->members)));
    }

    public function createConversation(): void
    {
        $actor = $this->authorizeOwner();
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->members), fn ($id) => $id !== $actor->id)));
        $this->validate(['members' => 'required|array|min:1|max:50', 'members.*' => 'integer|distinct|exists:users,id']);
        $kind = count($ids) === 1 ? 'direct' : 'group';
        $title = trim($this->groupTitle);
        if ($kind === 'group' && $title === '') {
            $title = 'Group · '.now()->format('M j');
        }
        $thread = app(TeamCommunications::class)->start($actor, [
            'kind' => $kind,
            'title' => $title,
            'members' => $ids,
        ]);
        $this->creating = false;
        $this->reset('members', 'groupTitle', 'peopleSearch', 'selectedRoles');
        $this->openConversation($thread->id);
    }

    public function updatedAttachments(): void
    {
        $this->authorizeOwner();
        $this->validate([
            'attachments' => 'array|max:3',
            'attachments.*' => 'file|max:10240|mimes:pdf,png,jpg,jpeg,webp,txt,csv|extensions:pdf,png,jpg,jpeg,webp,txt,csv',
        ]);
    }

    public function send(): void
    {
        $actor = $this->authorizeOwner();
        abort_unless($this->conversationId, 404);
        app(TeamCommunications::class)->send($actor, $this->conversationId, $this->body, $this->clientKey, $this->attachments);
        $this->body = '';
        $this->attachments = [];
        $this->clientKey = (string) Str::uuid();
        $this->dispatch('quick-message-sent');
    }

    public function render()
    {
        $actor = $this->authorizeOwner();
        $service = app(TeamCommunications::class);
        $threads = $service->inbox($actor)->orderByDesc('last_message_at')->limit(8)->get();
        $selected = $this->conversationId ? $service->conversation($actor, $this->conversationId) : null;
        $messages = $selected?->messages()->with(['sender:id,name', 'attachments'])->latest('id')->limit(40)->get()->reverse();

        $people = $this->creating ? User::query()->whereKeyNot($actor->id)->when(trim($this->peopleSearch) !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes(mb_substr(trim($this->peopleSearch), 0, 100), '%_\\\\').'%'))->orderBy('name')->limit(40)->get(['id', 'name']) : collect();
        $roles = $this->creating ? \Spatie\Permission\Models\Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']) : collect();
        $selectedPeople = $this->creating && count($this->members) ? User::query()->whereIn('id', $this->members)->get(['id', 'name']) : collect();
        return view('livewire.quick-messages', compact('threads', 'selected', 'messages', 'people', 'roles', 'selectedPeople'));
    }
}
