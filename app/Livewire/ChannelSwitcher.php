<?php

namespace App\Livewire;

use App\Support\ChannelContext;
use Livewire\Component;

/**
 * Topbar control letting an admin pick which channel's data they're viewing
 * (or "All Channels"). Purely a session-based view filter — see
 * App\Support\ChannelContext and the resources that opt into scoping by it.
 */
class ChannelSwitcher extends Component
{
    public ?int $activeChannelId = null;

    public function mount(): void
    {
        $this->activeChannelId = ChannelContext::currentId();
    }

    public function updatedActiveChannelId(): void
    {
        ChannelContext::setActive($this->activeChannelId);

        // Filament resource queries are built on page load, so a client-side
        // redirect is the simplest way to guarantee every list picks up the
        // new scope immediately.
        $this->redirect(request()->header('Referer') ?: '/admin', navigate: false);
    }

    public function render()
    {
        return view('livewire.channel-switcher', [
            'channels' => ChannelContext::available(),
        ]);
    }
}
