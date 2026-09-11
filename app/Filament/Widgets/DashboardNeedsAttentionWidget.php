<?php

namespace App\Filament\Widgets;

class DashboardNeedsAttentionWidget extends NeedsAttentionWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];
}
