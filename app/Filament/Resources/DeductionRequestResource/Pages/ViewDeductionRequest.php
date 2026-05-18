<?php

namespace App\Filament\Resources\DeductionRequestResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Models\InventoryStock;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewDeductionRequest extends ViewRecord
{
    protected static string $resource = DeductionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending' && auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->action(function () {
                    $svc = app(ReconciliationService::class);
                    $available = $svc->getStockAvailable($this->record);

                    if ($available < $this->record->quantity) {
                        Notification::make()
                            ->title('Insufficient stock')
                            ->body("Only {$available} units available at {$this->record->location->name}. Requested: {$this->record->quantity}.")
                            ->warning()
                            ->send();
                        return;
                    }

                    $svc->approve($this->record);
                    Notification::make()->title('Deduction approved.')->success()->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending' && auth()->user()?->isAdmin())
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Reason for rejection')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    app(ReconciliationService::class)->reject($this->record, $data['rejection_reason']);
                    Notification::make()->title('Deduction rejected.')->warning()->send();
                }),
        ];
    }
}
