<?php

namespace App\Filament\Widgets;

use App\Services\StreamerPayEstimate;
use App\Support\BusinessTime;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * A streamer's pay for the current pay period while it is still running:
 * the estimated total, what is already on a pay run, and each show.
 * The arrows step back to earlier periods.
 */
class StreamerPayPeriodWidget extends Widget
{
    protected string $view = 'filament.widgets.streamer-pay-period';
    protected static ?int $sort = -1;
    protected static bool $isLazy = true;
    protected int | string | array $columnSpan = 'full';

    /** Periods back from the current one (0 = this period). */
    public int $back = 0;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner() && $user->streamer);
    }

    public function shift(int $by): void
    {
        $this->back = max(0, min(26, $this->back - $by));
    }

    public function estimate(): ?array
    {
        $streamer = auth()->user()?->streamer;
        if (! $streamer) return null;
        $date = BusinessTime::local();
        // Step back one period at a time from today.
        for ($i = 0; $i < $this->back; $i++) {
            [$start] = \App\Support\PaySchedule::periodFor($date, $streamer->business_id);
            $date = Carbon::parse($start)->subDay();
        }

        return app(StreamerPayEstimate::class)->forPeriod($streamer, $date);
    }
}
