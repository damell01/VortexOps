<?php

namespace App\Livewire;

use App\Services\TeamCommunications;
use Livewire\Attributes\On;
use Livewire\Component;

class AnnouncementPreview extends Component
{
    public function mount(): void { abort_unless(TeamCommunications::canAccess(auth()->user()), 403); }
    #[On('announcement-preview-changed')]
    public function refreshPreview(): void { abort_unless(TeamCommunications::canAccess(auth()->user()), 403); }
    public function render()
    {
        abort_unless(TeamCommunications::canAccess(auth()->user()), 403);
        return view('livewire.announcement-preview', ['banners' => app(TeamCommunications::class)->banners(auth()->user())]);
    }
}
