<?php

namespace App\Filament\Resources\MissingItemReportResource\Pages;

use App\Filament\Resources\MissingItemReportResource;
use App\Models\MissingItemReport;
use Filament\Resources\Pages\CreateRecord;

class CreateMissingItemReport extends CreateRecord
{
    protected static string $resource = MissingItemReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by'] = auth()->id();

        // Auto-calculate total value
        if (isset($data['expected_quantity'], $data['unit_cost'])) {
            $data['total_value'] = $data['expected_quantity'] * ($data['unit_cost'] ?? 0);
        }

        return $data;
    }
}
