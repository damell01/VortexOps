<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\User;

class ManagerProfitShare extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Profit Share Approvals';
    protected static ?string $navigationGroup = 'Management';
    protected static ?int $navigationSort = 30;

    public function getTitle(): string
    {
        return 'Profit Share Packet Reviews';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->managedStreamers()->exists()) ?? false;
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
