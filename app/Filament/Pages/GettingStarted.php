<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class GettingStarted extends Page
{
    protected static ?string $title = 'Getting Started';

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.pages.getting-started';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
