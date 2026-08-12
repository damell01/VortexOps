<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ShowPipelineStatusWidget extends Widget
{
    protected static ?int $sort = -20;
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-pipeline-status';

    public ?Model $record = null;
}
