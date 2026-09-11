<?php

namespace App\Filament\Widgets;

class DashboardShowsKpiWidget extends ShowsKpiWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        return array_slice(parent::getStats(), 0, 4);
    }
}
