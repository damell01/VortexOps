<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\WhatnotLedgerResource\Pages;
use App\Models\WhatnotLedgerEntry;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class WhatnotLedgerResource extends Resource
{
    use HasAdminNavVisibility;

    protected static ?string $model = WhatnotLedgerEntry::class;

    protected static ?string $navigationLabel = 'Ledger';

    protected static ?string $modelLabel = 'ledger entry';

    protected static ?string $pluralModelLabel = 'ledger';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-currency-dollar';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return AdminModules::isEnabled('reporting')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool { return false; }

    // The list page draws its own table (filters, totals, details panel and
    // CSV export) — see Pages\ListWhatnotLedgerEntries.

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('channel')->inChannelContext();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatnotLedgerEntries::route('/'),
        ];
    }
}