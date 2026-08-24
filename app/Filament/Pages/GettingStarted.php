<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use Filament\Pages\Page;
use App\Support\NavVisibility;

class GettingStarted extends Page
{
    use RespectsRoleVisibility;

    protected static ?string $title = 'Getting Started';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.pages.getting-started';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }
}
