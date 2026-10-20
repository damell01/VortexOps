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
    public function dismiss(int $id): void
    {
        abort_unless(TeamCommunications::canAccess(auth()->user()), 403);
        $banner = app(TeamCommunications::class)->banners(auth()->user())->firstWhere('id', $id);
        abort_unless($banner && $banner->dismissible, 403);
        session()->put('communications.dismissed.'.$id, $banner->updated_at->timestamp);
    }
    public function render()
    {
        abort_unless(TeamCommunications::canAccess(auth()->user()), 403);
        return view('livewire.announcement-preview', ['banners' => app(TeamCommunications::class)->banners(auth()->user())->reject(fn ($b) => session('communications.dismissed.'.$b->id) === $b->updated_at->timestamp)]);
    }
}
