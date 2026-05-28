<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class OperationsGuide extends Page
{
    protected static ?string $title = 'Operations Guide';

    protected static ?string $slug = 'operations-guide';

    protected static bool $shouldRegisterNavigation = false;

    public function getView(): string
    {
        return 'filament.pages.operations-guide';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'High-level guide for how inventory, shows, deductions, and payouts are meant to connect.';
    }
}
