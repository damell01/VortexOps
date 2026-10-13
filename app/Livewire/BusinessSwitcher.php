<?php

namespace App\Livewire;

use App\Support\BusinessContext;
use Livewire\Component;

/**
 * Topbar control for which business payroll is being run for (or all of
 * them). Session-based, like the channel switcher beside it — see
 * App\Support\BusinessContext.
 */
class BusinessSwitcher extends Component
{
    public ?int $activeBusinessId = null;

    public function mount(): void
    {
        $this->activeBusinessId = BusinessContext::currentId();
    }

    public function updatedActiveBusinessId(): void
    {
        BusinessContext::setActive($this->activeBusinessId ?: null);

        $this->redirect(request()->header('Referer') ?: '/admin', navigate: false);
    }

    public function render()
    {
        return view('livewire.business-switcher', [
            'businesses' => BusinessContext::available(),
            'current' => BusinessContext::current(),
        ]);
    }
}
