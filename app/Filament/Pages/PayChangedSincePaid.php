<?php

namespace App\Filament\Pages;

use App\Services\PayChangedAfterPayout;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Shows whose earnings changed after their pay was locked on a finalized or
 * paid run, with what was paid, what the same calculation gives now and what
 * moved, so the difference can be settled on the next run.
 */
class PayChangedSincePaid extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Changed after payout';
    protected static ?string $navigationLabel = 'Changed after payout';
    protected static ?string $slug = 'payroll/changed-after-payout';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|UnitEnum|null $navigationGroup = 'Payroll';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && ! \App\Support\NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(PayChangedAfterPayout::class)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function getView(): string
    {
        return 'filament.pages.pay-changed-since-paid';
    }

    public function getSubheading(): ?string
    {
        return 'Shows whose Whatnot net, tips or report changed after their pay was finalized or paid. Settle the difference on the next run.';
    }

    /** "Check again": the view works the list out on every render. */
    public function refreshList(): void {}

    public function getViewData(): array
    {
        $rows = app(PayChangedAfterPayout::class)->refresh();
        $settlement = app(\App\Services\PayChangeSettlement::class);
        $targets = $rows->pluck('streamer_id')->unique()->mapWithKeys(function ($id) use ($settlement) {
            $streamer = \App\Models\Streamer::query()->find($id);
            $run = $streamer ? $settlement->targetRun($streamer) : null;

            return [$id => $run ? ['id' => $run->id, 'label' => '#' . $run->id . ' · ' . $run->week_start?->format('M j') . ' – ' . $run->week_end?->format('M j')] : null];
        });

        return ['rows' => $rows, 'targets' => $targets];
    }

    /** Carry one difference onto the person's next draft run, as an adjustment line with a note. */
    public function settle(int $showId, int $streamerId, string $note = ''): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $payout = app(\App\Services\PayChangeSettlement::class)->settle($showId, $streamerId, mb_substr(trim($note), 0, 500) ?: null, auth()->id());
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Filament\Notifications\Notification::make()->title('Not carried over')->body(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }
        \Filament\Notifications\Notification::make()->title('Carried to run #' . $payout->weekly_payout_batch_id)
            ->body(($payout->calculated_payout < 0 ? 'Deduction of $' : 'Adjustment of $') . number_format(abs((float) $payout->calculated_payout), 2) . ' added.')
            ->success()->send();
    }
}
