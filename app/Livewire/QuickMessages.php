<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\TeamCommunications;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuickMessages extends Component
{
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

    public function send(): void
    {
        $actor = $this->authorizeOwner();
        abort_unless($this->conversationId, 404);
        app(TeamCommunications::class)->send($actor, $this->conversationId, $this->body, $this->clientKey);
        $this->body = '';
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

        return view('livewire.quick-messages', compact('threads', 'selected', 'messages'));
    }
}
