<?php

namespace App\Console\Commands;

use App\Models\Show;
use Illuminate\Console\Command;

/**
 * How much of Whatnot's analytics actually arrives.
 *
 * "Show duration reads 0h 0m" has two explanations that look identical from
 * the show page — Whatnot reporting nothing, or the import losing it — and
 * they lead to completely different work. Both halves of the chain are now
 * covered by tests, so a field that is empty across every show points at the
 * scrape, and one that is empty on some points at Whatnot.
 *
 * Duration is the field worth watching: hourly and hybrid streamers are paid
 * against it, so an empty one is money computed from nothing.
 */
class WhatnotAnalyticsCoverage extends Command
{
    protected $signature = 'whatnot:analytics-coverage
                            {--channel= : Limit to one WhatnotChannel name or ID}
                            {--days=90  : Only look at shows from the last N days}';

    protected $description = 'Report which Whatnot analytics fields are arriving on imported shows';

    /** The fields Whatnot fills in, and whether a zero is a plausible answer. */
    private const FIELDS = [
        'show_duration'          => 'rarely',
        'start_time'             => 'never',
        'end_time'               => 'never',
        'units_sold'             => 'sometimes',
        'gross_revenue'          => 'sometimes',
        'whatnot_net'            => 'sometimes',
        'completed_earnings'     => 'sometimes',
        'avg_order_value'        => 'sometimes',
        'tips'                   => 'often',
        'giveaway_spend'         => 'often',
        'giveaways_count'        => 'often',
        'buyers_count'           => 'sometimes',
        'first_time_buyers'      => 'often',
        'returning_buyers'       => 'often',
        'shares_count'           => 'often',
        'max_concurrent_viewers' => 'rarely',
        'total_views'            => 'rarely',
        'avg_order_rating'       => 'never',
    ];

    public function handle(): int
    {
        $query = Show::query()
            ->where('import_source', 'auto_whatnot')
            ->where('show_date', '>=', now()->subDays((int) $this->option('days'))->toDateString());

        if ($channel = $this->option('channel')) {
            $query->whereHas('channel', fn ($q) => is_numeric($channel)
                ? $q->whereKey($channel)
                : $q->where('name', $channel)->orWhere('whatnot_username', $channel));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->warn('No Whatnot-imported shows in that window.');
            $this->line('Shows added by hand carry none of these fields and are excluded on purpose.');

            return self::SUCCESS;
        }

        $this->info("{$total} imported show(s) in the last " . $this->option('days') . ' days');
        $this->newLine();

        $rows = [];

        foreach (self::FIELDS as $field => $zeroExpected) {
            $missing = (clone $query)->whereNull($field)->count();
            $zero    = (clone $query)->where($field, 0)->count();
            $set     = $total - $missing - $zero;

            $rows[] = [
                $field,
                $set,
                $zero,
                $missing,
                $this->verdict($total, $set, $zero, $missing, $zeroExpected),
            ];
        }

        $this->table(['Field', 'Has a value', 'Zero', 'Missing', 'Reads as'], $rows);

        $this->newLine();
        $this->line('<fg=gray>Missing on every show points at the scrape — the field is not being read.</>');
        $this->line('<fg=gray>Missing on some points at Whatnot, which does not report every figure for every show.</>');
        $this->line('<fg=gray>Zero is an answer, not an absence: both halves of the import keep it deliberately.</>');

        return self::SUCCESS;
    }

    private function verdict(int $total, int $set, int $zero, int $missing, string $zeroExpected): string
    {
        if ($missing === $total) {
            return 'never arrives — check the scraper';
        }

        if ($zero === $total) {
            return $zeroExpected === 'never'
                ? 'always zero — suspicious'
                : 'always zero — plausible';
        }

        if ($missing > $set + $zero) {
            return 'mostly missing';
        }

        return 'arriving';
    }
}
