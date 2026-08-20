<?php

namespace App\Livewire;

use App\Models\Pallet;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The pallet's manifest, read-only, on the edit form.
 *
 * Its own component rather than markup inside the form so paging is local: a
 * paginator that reloaded the page would take any unsaved edits to the fields
 * above it with it, which is a steep price for looking at line eleven.
 *
 * Read-only on purpose. Editing happens in the manifest table, and two editors
 * for one thing is how they drift apart.
 */
class ManifestLinesSummary extends Component
{
    public Pallet $pallet;

    public int $page = 1;

    public int $perPage = 10;

    public function nextPage(): void
    {
        if ($this->page < $this->totalPages()) {
            $this->page++;
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function totalLines(): int
    {
        return $this->pallet->lines()->count();
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->totalLines() / $this->perPage));
    }

    public function render(): View
    {
        // Clamped rather than trusted: deleting lines elsewhere can leave the
        // page number past the end, and an empty table reads as no manifest.
        $this->page = min($this->page, $this->totalPages());

        return view('livewire.manifest-lines-summary', [
            'lines' => $this->pallet->lines()
                ->with('inventoryItem:id,name')
                ->orderBy('line_number')
                ->skip(($this->page - 1) * $this->perPage)
                ->take($this->perPage)
                ->get(),
            'total' => $this->totalLines(),
        ]);
    }
}
