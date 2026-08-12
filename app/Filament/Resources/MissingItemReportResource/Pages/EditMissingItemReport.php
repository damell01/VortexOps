<?php

namespace App\Filament\Resources\MissingItemReportResource\Pages;

use App\Filament\Resources\MissingItemReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMissingItemReport extends EditRecord
{
    protected static string $resource = MissingItemReportResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-update total value
        if (isset($data['expected_quantity'], $data['unit_cost'])) {
            $data['total_value'] = $data['expected_quantity'] * ($data['unit_cost'] ?? 0);
        }

        return $data;
    }
}
