<?php

namespace App\Support;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

/**
 * How every table in the panel presents its filters.
 *
 * Filament's default is a dropdown anchored to the toolbar button, one filter
 * per row. That is fine for two filters and wrong for eight: the panel grows
 * past the bottom of the window, so it covers the rows it is meant to narrow
 * and the Apply button ends up somewhere below the fold. Inventory has eight
 * filters and a query builder — reaching Apply there meant scrolling inside a
 * floating panel that was itself hanging off the page.
 *
 * So: past a couple of filters the panel becomes a dialog. A dialog is
 * centred, sized to the viewport, scrolls its own body, and keeps Apply and
 * Reset pinned to a footer that is always on screen. Below that count the
 * dropdown is still the lighter thing to open, and nothing about it is broken,
 * so it stays.
 *
 * Applied globally from AppServiceProvider, which means relation managers and
 * custom pages get it too, not only resources. Any table that wants something
 * else says so in its own table() method — those calls run after this one and
 * win.
 */
class TableFilterPresentation
{
    /**
     * More filters than this and the dropdown becomes the problem.
     *
     * Two single-line filters are roughly 200px of panel. Three starts to
     * depend on how tall the fields are, and a select with a search box is not
     * one line. Three is where the dialog starts paying for itself.
     */
    private const MAX_FILTERS_IN_A_DROPDOWN = 2;

    public static function apply(Table $table): void
    {
        $table
            ->filtersLayout(fn (Table $table): FiltersLayout => static::needsADialog($table)
                ? FiltersLayout::Modal
                : FiltersLayout::Dropdown)
            // Filament only spreads filters across columns for the
            // above/below-content layouts; a dialog gets one column and a lot
            // of vertical space unless it is told otherwise.
            ->filtersFormColumns(fn (Table $table): int | array => static::needsADialog($table)
                ? ['default' => 1, 'md' => 2, 'xl' => 3]
                : 1)
            // The width match in getFiltersFormWidth() only understands an int
            // column count, so an array of breakpoints leaves it null and the
            // dialog opens narrow. Name it.
            ->filtersFormWidth(fn (Table $table): Width => static::needsADialog($table)
                ? Width::FiveExtraLarge
                : Width::ExtraSmall)
            ->filtersTriggerAction(fn (Action $action): Action => $action
                // The default is an unlabelled icon button. A funnel glyph
                // alone is not obvious enough for a control this central.
                ->label('Filters')
                ->icon(Heroicon::Funnel)
                ->button()
                ->color('gray')
                ->modalHeading('Filters')
                ->modalDescription('Narrow the list down, then apply.')
                // Filters are deferred by default, so nothing happens until
                // Apply is pressed — which makes it the one control that must
                // never scroll out of reach.
                ->stickyModalHeader()
                ->stickyModalFooter());
    }

    private static function needsADialog(Table $table): bool
    {
        // Visible filters only: a filter hidden from this user is not taking
        // up any room in the panel they are about to open.
        return count($table->getFilters()) > self::MAX_FILTERS_IN_A_DROPDOWN;
    }
}
