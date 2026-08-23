<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * What Whatnot sold, against what the streamer logged.
 *
 * Two independent records of the same night that nowhere in the app compared.
 * Whatnot counts what buyers paid for; the End of Stream report counts what
 * the streamer physically handed over, giveaways and promos included — things
 * Whatnot has no order for at all.
 *
 * The number that matters is the difference, and the point of showing it is
 * not to catch anyone out. A shortfall usually means stock left the shelf
 * without being logged, which is what makes a later count look wrong; a
 * surplus usually means a lot was logged twice. Either way it is answerable
 * on the night and unanswerable a month later.
 */
class ShowItemReconciliationWidget extends Widget
{
    protected static ?int $sort = -25;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.show-item-reconciliation';

    public ?Model $record = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isStreamer()) ?? false;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRowsProperty(): array
    {
        $show = $this->record;

        return $show instanceof Show ? $show->itemReconciliation() : [];
    }

    public function getReportFiledProperty(): bool
    {
        $show = $this->record;

        return $show instanceof Show && $show->itemReportIsFiled();
    }

    /**
     * The one-line summary, so nobody has to read a table to learn there is
     * nothing to look at.
     */
    public function getVerdictProperty(): array
    {
        if (! $this->reportFiled) {
            return ['tone' => 'idle', 'text' => 'No End of Stream report filed yet.'];
        }

        $differences = collect($this->rows)
            ->filter(fn ($row) => $row['difference'] !== null && $row['difference'] !== 0);

        if ($differences->isEmpty()) {
            return ['tone' => 'match', 'text' => 'Logged items match Whatnot.'];
        }

        $parts = $differences->map(function (array $row): string {
            $count = abs($row['difference']);
            $noun  = \Illuminate\Support\Str::plural('item', $count);

            return $row['difference'] < 0
                ? "{$count} {$noun} sold on Whatnot but not logged as " . strtolower($row['label'])
                : "{$count} {$noun} logged as " . strtolower($row['label']) . ' beyond what Whatnot recorded';
        });

        return ['tone' => 'differs', 'text' => ucfirst($parts->join('; '))];
    }
}
