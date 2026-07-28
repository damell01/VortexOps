<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\Show;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class AddShowItems extends Page
{
    protected static string $resource = ShowResource::class;

    protected static ?string $title = 'Add Items';

    public Show $record;

    public ?int $inventory_item_id = null;

    public int $quantity = 1;

    public function getView(): string
    {
        return 'filament.pages.add-show-items';
    }

    public function mount(Show $record): void
    {
        $record->loadMissing('streamers');

        $user = auth()->user();

        // Admins can add items to any show. Streamers only to shows they're
        // actually assigned to — this page writes deduction lines, so it
        // can't rely on the generic module gate alone.
        abort_unless(
            ($user?->isAdmin() ?? false)
                || (($user?->isStreamer() ?? false) && $user->streamer && $record->streamers->contains('id', $user->streamer->id)),
            403,
        );

        $this->record = $record;
    }

    public function getInventoryItemsProperty(): Collection
    {
        return InventoryItem::where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function getLinesProperty(): Collection
    {
        $this->record->load('latestDeductionRequest.lines.inventoryItem');

        return $this->record->latestDeductionRequest?->lines ?? collect();
    }

    public function addItem(): void
    {
        $this->validate([
            'inventory_item_id' => ['required', 'exists:products,id'],
            'quantity'          => ['required', 'integer', 'min:1'],
        ]);

        $this->record->load('latestDeductionRequest', 'streamers');

        $dr = $this->record->latestDeductionRequest;

        // A processed/rejected request is done — start a fresh one rather
        // than appending to a closed-out record.
        if (! $dr || in_array($dr->status, ['processed', 'rejected'])) {
            $dr = DeductionRequest::create([
                'show_id'     => $this->record->id,
                'streamer_id' => auth()->user()?->streamer?->id ?? $this->record->streamers->first()?->id,
                'status'      => 'draft',
            ]);
        }

        // No cost yet — streamers are just logging what went out during the
        // show. Ops fills in unit_cost_snapshot during review/approval.
        DeductionRequestLine::create([
            'deduction_request_id'  => $dr->id,
            'inventory_item_id'     => $this->inventory_item_id,
            'inventory_location_id' => $this->record->defaultInventoryLocation()?->id,
            'quantity_suggested'    => $this->quantity,
            'quantity_approved'     => $this->quantity,
            'unit_cost_snapshot'    => 0,
            'line_total'            => 0,
            'ai_confidence'         => 'manual',
            'ops_overridden'        => false,
        ]);

        if ($this->record->status === 'draft') {
            $this->record->update(['status' => 'pending_review']);
        }

        $this->reset(['inventory_item_id', 'quantity']);
        $this->quantity = 1;

        Notification::make()->title('Item added')->success()->send();
    }

    public function removeLine(int $lineId): void
    {
        $this->record->load('latestDeductionRequest');
        $dr = $this->record->latestDeductionRequest;

        if (! $dr || $dr->status === 'processed') {
            return;
        }

        $dr->lines()->whereKey($lineId)->delete();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Show')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ShowResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
