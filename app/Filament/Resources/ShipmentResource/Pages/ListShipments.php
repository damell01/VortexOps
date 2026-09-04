<?php

namespace App\Filament\Resources\ShipmentResource\Pages;

use App\Filament\Resources\ShipmentResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;

class ListShipments extends ListRecords
{
    protected static string $resource = ShipmentResource::class;

    /**
     * Keep the show boundary as Livewire component state.
     *
     * Resource::getEloquentQuery() can see ?show= on the initial document
     * request, but Filament's deferred/pagination requests are POSTs to
     * /livewire/update and no longer carry the browser query string. Without a
     * URL-bound property the first AJAX table load silently falls back to every
     * shipment in the channel even though the address bar still says
     * ?show=<id>.
     */
    #[Url(as: 'show')]
    public ?int $show = null;

    /**
     * Apply the selected show at the Livewire page layer so it survives every
     * pagination, sorting, searching, and deferred-loading request.
     */
    protected function getTableQuery(): Builder | Relation | null
    {
        $query = parent::getTableQuery();

        if ($query && $this->show && $this->show > 0) {
            $query->where('shipments.show_id', $this->show);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
