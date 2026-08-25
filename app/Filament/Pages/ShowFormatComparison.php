<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\ShowFormatAnalytics;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Which kinds of break are worth running.
 *
 * Every average in the app was taken across every show, so formats that
 * behave nothing alike shared one mean — a sudden death and a giveaway-heavy
 * night landed in the same number, and the number described neither. This
 * puts them side by side, which is the only form in which "is sudden death
 * worth it?" has an answer.
 */
class ShowFormatComparison extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';

    protected static ?string $title           = 'Show Format Comparison';
    protected static ?string $navigationLabel = 'Show Formats';

    public string $range     = '90';
    public string $channelId = '';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-scale';
    }

    public function getView(): string
    {
        return 'filament.pages.show-format-comparison';
    }

    public function getSubheading(): ?string
    {
        return 'How each kind of break performs against the others. Classify a show from its Notes section.';
    }

    /** @return array<string, string> */
    public function rangeOptions(): array
    {
        return [
            '30'  => 'Last 30 days',
            '90'  => 'Last 90 days',
            '365' => 'Last 12 months',
            ''    => 'All time',
        ];
    }

    public function channelOptions(): array
    {
        return ['' => 'All channels'] + WhatnotChannel::orderBy('name')->pluck('name', 'id')->all();
    }

    private function from(): ?Carbon
    {
        return $this->range === '' ? null : now()->subDays((int) $this->range)->startOfDay();
    }

    public function getRowsProperty(): array
    {
        return app(ShowFormatAnalytics::class)->compare(
            $this->from(),
            null,
            $this->channelId === '' ? null : (int) $this->channelId,
        );
    }

    public function getOverallProperty(): array
    {
        return app(ShowFormatAnalytics::class)->overall(
            $this->from(),
            null,
            $this->channelId === '' ? null : (int) $this->channelId,
        );
    }

    /** Shows still waiting on a classification, so the gap is visible. */
    public function getUnclassifiedCountProperty(): int
    {
        foreach ($this->rows as $row) {
            if ($row['format'] === null) {
                return $row['shows'];
            }
        }

        return 0;
    }

    public function getFormatLabelsProperty(): array
    {
        return Show::formatLabels();
    }
}
