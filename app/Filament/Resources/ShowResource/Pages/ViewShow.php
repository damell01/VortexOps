<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\DeductionRequestResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review_approval')
                ->label('Review Approval')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(fn () => in_array($this->record->status, ['pending_approval', 'reconciled', 'closed']))
                ->url(fn () => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),

            EditAction::make(),
        ];
    }
}
