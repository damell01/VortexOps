<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use Filament\Pages\Page;
use App\Models\User;
use App\Support\NavVisibility;

class ManagerProfitShare extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Profit Share Approvals';
    protected static ?int $navigationSort = 30;

    public function getTitle(): string
    {
        return 'Profit Share Packet Reviews';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Management';
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

    public function getView(): string
    {
        return 'filament.pages.manager-profit-share';
    }

    public function getManager(): User
    {
        return auth()->user() ?? abort(403);
    }
}
