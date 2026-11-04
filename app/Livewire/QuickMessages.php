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
    public string $kind = 'direct';
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

    public function createConversation(): void
    {
        $actor = $this->authorizeOwner();
        $thread = app(TeamCommunications::class)->start($actor, [
            'kind' => $this->kind,
            'title' => trim($this->groupTitle),
            'members' => $this->members,
        ]);
        $this->creating = false;
        $this->reset('members', 'groupTitle', 'peopleSearch');
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
        $messages = $selected?->messages()->with('sender:id,name')->latest('id')->limit(40)->get()->reverse();

        $people = $this->creating ? User::query()->whereKeyNot($actor->id)->when(trim($this->peopleSearch) !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes(mb_substr(trim($this->peopleSearch), 0, 100), '%_\\\\').'%'))->orderBy('name')->limit(40)->get(['id', 'name']) : collect();
        return view('livewire.quick-messages', compact('threads', 'selected', 'messages', 'people'));
    }
}
