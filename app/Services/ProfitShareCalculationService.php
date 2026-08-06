<?php

namespace App\Services;

use App\Models\ProfitSharePacket;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use Carbon\Carbon;

class ProfitShareCalculationService
{
    public function calculateForMonth(Streamer $streamer, int $year, int $month): array
    {
        $logs = StreamerLogEntry::where('streamer_id', $streamer->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', '!=', 'draft')
            ->get();

        $totals = [
            'gross_revenue' => $logs->sum('gross_revenue'),
            'product_cost' => $logs->sum('product_cost'),
            'shipping_cost' => $logs->sum(fn($log) => $log->show?->shippingSurcharges()->sum('amount') ?? 0),
            'hours_worked' => $logs->sum('hours_streamed'),
            'num_shows' => $logs->count(),
        ];

        $totals['net_profit'] = max(0, $totals['gross_revenue'] - $totals['product_cost'] - $totals['shipping_cost']);

        // Calculate based on streamer's payout type
        $totals['profit_share_amount'] = $this->calculateProfitShare($streamer, $totals);
        $totals['hourly_earnings'] = $this->calculateHourlyEarnings($streamer, $totals);

        return $totals;
    }

    public function calculateProfitShare(Streamer $streamer, array $totals): float
    {
        if (!$streamer->profit_share_pct) {
            return 0;
        }

        return round($totals['net_profit'] * ($streamer->profit_share_pct / 100), 2);
    }

    public function calculateHourlyEarnings(Streamer $streamer, array $totals): float
    {
        if (!$streamer->hourly_rate || $totals['hours_worked'] <= 0) {
            return 0;
        }

        return round($totals['hours_worked'] * $streamer->hourly_rate, 2);
    }

    public function getOrCreatePacket(Streamer $streamer, int $year, int $month): ProfitSharePacket
    {
        $packet = ProfitSharePacket::where('streamer_id', $streamer->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if (!$packet) {
            $packet = ProfitSharePacket::create([
                'streamer_id' => $streamer->id,
                'year' => $year,
                'month' => $month,
                'status' => 'draft',
            ]);
        }

        return $packet;
    }

    public function updatePacketWithCalculations(ProfitSharePacket $packet): void
    {
        $calculations = $this->calculateForMonth(
            $packet->streamer,
            $packet->year,
            $packet->month
        );

        $packet->update([
            'gross_revenue' => $calculations['gross_revenue'],
            'product_cost' => $calculations['product_cost'],
            'shipping_cost' => $calculations['shipping_cost'],
            'hours_worked' => $calculations['hours_worked'],
            'profit_share_amount' => $calculations['profit_share_amount'],
            'hourly_earnings' => $calculations['hourly_earnings'],
        ]);
    }

    public function isMonthFinalized(int $month, int $year): bool
    {
        $lastDayOfMonth = Carbon::create($year, $month, 1)->endOfMonth();
        return now()->isAfter($lastDayOfMonth);
    }

    public function getMonthProgress(int $month, int $year): float
    {
        $now = now();
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        if ($now->isBefore($startOfMonth)) {
            return 0;
        }

        if ($now->isAfter($endOfMonth)) {
            return 100;
        }

        $totalDays = $startOfMonth->diffInDays($endOfMonth) + 1;
        $elapsedDays = $startOfMonth->diffInDays($now) + 1;

        return round(($elapsedDays / $totalDays) * 100);
    }

    public function getDaysUntilMonthEnd(): int
    {
        $endOfMonth = now()->endOfMonth();
        return max(0, now()->diffInDays($endOfMonth));
    }

    public function finalizePacket(ProfitSharePacket $packet): void
    {
        // Update calculations one final time
        $this->updatePacketWithCalculations($packet);

        // Mark as ready for final review (still in draft but updated)
        $packet->update([
            'status' => 'pending_review',
        ]);
    }
}
