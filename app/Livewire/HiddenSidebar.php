<?php

namespace App\Livewire;

use Filament\Livewire\Sidebar;
use Illuminate\Contracts\View\View;

/**
 * Filament's sidebar, without building it.
 *
 * Signed-in pages navigate with the custom top menu (desktop-mega-nav) and its
 * mobile sheet, and CSS hides .fi-sidebar wherever those render — which is
 * every signed-in page. Filament still built the whole sidebar regardless:
 * every navigation group and item, a second channel switcher, the account card.
 * That was about a fifth of each page's server time for markup nobody saw.
 */
class HiddenSidebar extends Sidebar
{
    public function render(): View
    {
        return view('filament.components.hidden-sidebar');
    }
}
