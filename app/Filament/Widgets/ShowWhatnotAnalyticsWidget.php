<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything Whatnot reported about a show, in one place.
 *
 * All of it was already imported — twenty-two fields on every scrape — and
 * seven of them reached the screen. Buyers, first-time buyers, shares, views,
 * peak viewers and the rest sat in columns nobody could read without opening
 * the database.
 *
 * This sits below the decisions. The metrics strip and the reconciliation
 * above it are what someone acts on; this is the reference they check against
 * when a number up there looks wrong.
 */
class ShowWhatnotAnalyticsWidget extends Widget
{
    protected static ?int $sort = 20;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.show-whatnot-analytics';

    public ?Model $record = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isStreamer()) ?? false;
    }

    /** @return array<string, array<int, array{label: string, value: string|null, hint: string|null}>> */
    public function getGroupsProperty(): array
    {
        $show = $this->record;

        return $show instanceof Show ? $show->whatnotAnalytics() : [];
    }

    /**
     * Whether Whatnot has told us anything at all.
     *
     * Asked of the import source rather than of the values, because several of
     * these columns are NOT NULL and default to zero — so a show added by hand
     * reports $0.00 sales and 0 orders, and reading the values would call that
     * "analytics we have" and print a panel of zeroes Whatnot never sent.
     */
    public function getHasAnyProperty(): bool
    {
        $show = $this->record;

        return $show instanceof Show && $show->import_source === 'auto_whatnot';
    }

    public function getSyncedAtProperty(): ?string
    {
        $show = $this->record;

        if (! $show instanceof Show) {
            return null;
        }

        $at = $show->getAttribute('last_analytics_synced_at');

        return $at ? Carbon::parse($at)->diffForHumans() : null;
    }
}
