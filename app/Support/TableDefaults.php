<?php

namespace App\Support;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * What every table does unless it says otherwise (its own table() runs after
 * this and wins):
 *  - filters, search and sort are kept for the session, so Back (or coming
 *    back later) lands on the list exactly as it was left;
 *  - an empty table says why and offers the next step: clear the filters and
 *    search when they hide everything, or create the first record.
 */
final class TableDefaults
{
    public static function apply(Table $table): void
    {
        $table
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistSortInSession()
            ->emptyStateDescription(fn (HasTable $livewire): string => self::narrowed($livewire)
                ? 'Nothing matches the filters or search. Clear them to see everything.'
                : 'Nothing here yet.')
            ->emptyStateActions([
                Action::make('vxClearFilters')
                    ->label('Clear filters & search')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->visible(fn (HasTable $livewire): bool => self::narrowed($livewire))
                    ->action(fn (HasTable $livewire) => $livewire->removeTableFilters()),
                Action::make('vxCreateFirst')
                    ->label(fn (HasTable $livewire): string => 'New ' . self::label($livewire))
                    ->icon('heroicon-m-plus')
                    ->visible(fn (HasTable $livewire): bool => ! self::narrowed($livewire) && self::createUrl($livewire) !== null)
                    ->url(fn (HasTable $livewire): ?string => self::createUrl($livewire)),
            ]);
    }

    private static function narrowed(HasTable $livewire): bool
    {
        try {
            // A quick-filter chip other than the first counts as narrowing too.
            $tab = property_exists($livewire, 'activeTab') ? $livewire->activeTab : null;
            $firstTab = method_exists($livewire, 'getCachedTabs') ? array_key_first($livewire->getCachedTabs()) : null;
            $byTab = filled($tab) && $firstTab !== null && (string) $tab !== (string) $firstTab;

            return $byTab || $livewire->getTable()->isFiltered() || filled($livewire->getTableSearch());
        } catch (\Throwable) {
            return false;
        }
    }

    private static function createUrl(HasTable $livewire): ?string
    {
        if (! $livewire instanceof ListRecords) return null;
        $resource = $livewire::getResource();
        try {
            return $resource::hasPage('create') && $resource::canCreate() ? $resource::getUrl('create') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function label(HasTable $livewire): string
    {
        return $livewire instanceof ListRecords ? $livewire::getResource()::getModelLabel() : 'record';
    }
}
