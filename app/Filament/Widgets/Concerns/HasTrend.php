<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Period-over-period trend indicator for StatsOverviewWidget stats, matching
 * the ↑/↓ + percent + color convention already used on the Streamer Analytics
 * table (StreamerAnalytics::priorPeriodGrossByStreamer()). No baseline (prior
 * period was zero) means no trend is shown rather than a misleading ±100%.
 */
trait HasTrend
{
    private function trendPct(float $current, float $prior): ?float
    {
        if ($prior <= 0) {
            return null;
        }

        return round((($current - $prior) / $prior) * 100, 1);
    }

    private function trendSuffix(float $current, float $prior, string $periodLabel = 'last week'): string
    {
        $pct = $this->trendPct($current, $prior);

        if ($pct === null) {
            return '';
        }

        $arrow = $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '→');

        return ' · ' . $arrow . ' ' . number_format(abs($pct), 1) . "% vs {$periodLabel}";
    }

    private function trendIcon(float $current, float $prior): ?string
    {
        $pct = $this->trendPct($current, $prior);

        return match (true) {
            $pct === null => null,
            $pct > 0 => 'heroicon-o-arrow-trending-up',
            $pct < 0 => 'heroicon-o-arrow-trending-down',
            default => null,
        };
    }

    private function trendColor(float $current, float $prior, string $default): string
    {
        $pct = $this->trendPct($current, $prior);

        return match (true) {
            $pct === null => $default,
            $pct > 0 => 'success',
            $pct < 0 => 'danger',
            default => $default,
        };
    }
}
