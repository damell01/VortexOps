<?php

namespace App\Console\Commands;

use App\Models\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RepairWhatnotShows extends Command
{
    protected $signature = 'whatnot:repair-shows
                            {--apply : Apply repairs; otherwise run as a dry-run}
                            {--skip-sync : Do not run a fresh show-index sync first}
                            {--aliases-only : Run lightweight recurring cleanup instead of historical merging}
                            {--max-future-days=120 : Reject auto-imported shows farther in the future than this}';

    protected $description = 'Repair duplicate/bad Whatnot show imports and enforce sane future scheduling';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $maxFutureDays = max(30, min(365, (int) $this->option('max-future-days')));
        $syncStartedAt = now()->subSecond();

        if (! $this->option('skip-sync') && ! $this->option('aliases-only')) {
            $this->info('Refreshing the authoritative Whatnot show index first…');
            $code = Artisan::call('whatnot:sync-show-index', ['--enrich' => 0]);
            $this->output->write(Artisan::output());
            if ($code !== self::SUCCESS) {
                $this->error('The show-index sync failed, so no repair was attempted.');
                return self::FAILURE;
            }
        }

        $summary = [
            'alias_rows_removed' => 0,
            'legacy_rows_merged' => 0,
            'same_day_duplicates_merged' => 0,
            'stale_future_removed' => 0,
        ];

        // Known duplicate UUIDs never get to survive a scheduled cleanup cycle.
        if (Schema::hasTable('whatnot_show_aliases')) {
            foreach (DB::table('whatnot_show_aliases')->get() as $alias) {
                $duplicate = Show::query()->where('whatnot_show_id', $alias->duplicate_whatnot_show_id)->first();
                $canonical = Show::find($alias->canonical_show_id);
                if (! $duplicate || ! $canonical || $duplicate->id === $canonical->id) continue;

                $this->line("Alias duplicate #{$duplicate->id} {$duplicate->whatnot_show_id} → canonical #{$canonical->id}");
                if ($apply) $this->mergeShow($duplicate, $canonical);
                $summary['alias_rows_removed']++;
            }
        }

        // Whatnot does not need to populate VortexOps with arbitrary year-out rows.
        // Enforce this on EVERY cleanup, including the lightweight scheduled one,
        // so malformed dates such as March 2027 disappear even if last_synced_at
        // was just touched by the scraper.
        $futureCutoff = today()->addDays($maxFutureDays);
        $impossibleFuture = Show::query()
            ->where('import_source', 'auto_whatnot')
            ->whereDate('show_date', '>', $futureCutoff)
            ->get();

        foreach ($impossibleFuture as $bad) {
            $this->line("Invalid future show #{$bad->id} {$bad->show_date?->format('Y-m-d')} {$bad->title}");
            if ($apply) $this->deleteShowGraph($bad);
            $summary['stale_future_removed']++;
        }

        if ($this->option('aliases-only')) {
            return $this->finish($summary, $apply);
        }

        // Merge old pre-UUID rows into an exact title/date UUID-backed record.
        $legacy = Show::query()
            ->where('import_source', 'auto_whatnot')
            ->whereNull('whatnot_show_id')
            ->whereNotNull('title')
            ->whereNotNull('show_date')
            ->orderByDesc('show_date')
            ->get();

        foreach ($legacy as $old) {
            $matches = Show::query()
                ->where('import_source', 'auto_whatnot')
                ->whereNotNull('whatnot_show_id')
                ->whereDate('show_date', $old->show_date)
                ->get()
                ->filter(fn (Show $candidate) => $this->normalizeTitle($candidate->title) === $this->normalizeTitle($old->title));

            if ($matches->count() !== 1) continue;
            $canonical = $matches->first();
            $this->line("Legacy duplicate #{$old->id} (NO UUID) → #{$canonical->id} {$canonical->whatnot_show_id}");
            if ($apply) $this->mergeShow($old, $canonical);
            $summary['legacy_rows_merged']++;
        }

        // Exact same title + date imported rows are duplicate records for this
        // integration. Retain the richer canonical record and remember discarded
        // UUIDs so recurring sync cleanup cannot leave them behind again.
        $groups = Show::query()
            ->where('import_source', 'auto_whatnot')
            ->whereNotNull('title')
            ->whereNotNull('show_date')
            ->get()
            ->groupBy(fn (Show $show) => $show->show_date?->format('Y-m-d') . '|' . $this->normalizeTitle($show->title))
            ->filter(fn ($rows) => $rows->count() > 1);

        foreach ($groups as $rows) {
            $rows = $rows->sortByDesc(fn (Show $show) => $this->canonicalScore($show))->values();
            $canonical = $rows->first();

            foreach ($rows->slice(1) as $candidate) {
                $duplicate = Show::find($candidate->id);
                if (! $duplicate || $duplicate->id === $canonical->id) continue;

                $this->line("Same-day duplicate #{$duplicate->id} " . ($duplicate->whatnot_show_id ?: 'NO UUID') . " → #{$canonical->id}");

                if ($apply) {
                    if ($duplicate->whatnot_show_id && Schema::hasTable('whatnot_show_aliases')) {
                        DB::table('whatnot_show_aliases')->updateOrInsert(
                            ['duplicate_whatnot_show_id' => $duplicate->whatnot_show_id],
                            [
                                'canonical_show_id' => $canonical->id,
                                'reason' => 'same_title_same_date_duplicate',
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                    $this->mergeShow($duplicate, $canonical);
                }
                $summary['same_day_duplicates_merged']++;
            }
        }

        // After a fresh authoritative index refresh, also remove ordinary future
        // auto-import rows that were NOT touched by that refresh. This handles
        // deleted/cancelled Whatnot schedules inside the allowed future horizon.
        if (! $this->option('skip-sync')) {
            $staleFuture = Show::query()
                ->where('import_source', 'auto_whatnot')
                ->whereDate('show_date', '>', today())
                ->where(function ($q) use ($syncStartedAt) {
                    $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $syncStartedAt);
                })
                ->get();

            foreach ($staleFuture as $bad) {
                $this->line("Stale future row #{$bad->id} {$bad->show_date?->format('Y-m-d')} {$bad->title}");
                if ($apply) $this->deleteShowGraph($bad);
                $summary['stale_future_removed']++;
            }
        }

        return $this->finish($summary, $apply);
    }

    private function finish(array $summary, bool $apply): int
    {
        $this->newLine();
        $this->table(
            ['Mode', 'Alias rows', 'Legacy merged', 'Same-day merged', 'Bad future removed'],
            [[
                $apply ? 'APPLIED' : 'DRY RUN',
                $summary['alias_rows_removed'],
                $summary['legacy_rows_merged'],
                $summary['same_day_duplicates_merged'],
                $summary['stale_future_removed'],
            ]]
        );
        if (! $apply) $this->comment('Re-run with --apply to perform these repairs.');
        return self::SUCCESS;
    }

    private function normalizeTitle(?string $title): string
    {
        return Str::of((string) $title)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function canonicalScore(Show $show): int
    {
        $analytics = collect([
            $show->gross_revenue,
            $show->completed_earnings,
            $show->units_sold,
            $show->buyers_count,
            $show->total_views,
        ])->filter(fn ($v) => $v !== null)->count();

        return ($show->whatnot_show_id ? 100000 : 0)
            + ($show->shipments()->count() * 100)
            + ($show->orders()->count() * 100)
            + ($analytics * 10)
            + min($show->id, 9);
    }

    private function mergeShow(Show $duplicate, Show $canonical): void
    {
        DB::transaction(function () use ($duplicate, $canonical): void {
            foreach ([
                'gross_revenue','whatnot_net','completed_earnings','units_sold','avg_order_value',
                'giveaway_spend','giveaways_count','buyers_count','first_time_buyers',
                'returning_buyers','shares_count','show_duration','max_concurrent_viewers',
                'total_views','avg_order_rating','last_analytics_synced_at','last_shipments_synced_at',
                'last_orders_synced_at',
            ] as $field) {
                if ($canonical->{$field} === null && $duplicate->{$field} !== null) {
                    $canonical->{$field} = $duplicate->{$field};
                }
            }
            $canonical->saveQuietly();

            $this->moveSimpleForeignKeys($duplicate->id, $canonical->id);
            $this->mergePivot('show_streamer', 'streamer_id', $duplicate->id, $canonical->id);
            $this->mergePivot('show_fulfillment_user', 'user_id', $duplicate->id, $canonical->id);

            $duplicate->refresh();
            $this->deleteShowGraph($duplicate);
        });
    }

    private function moveSimpleForeignKeys(int $from, int $to): void
    {
        foreach ([
            'shipments','whatnot_show_orders','show_ingestion_logs','show_change_logs',
            'deduction_requests','payouts','shipping_surcharges','show_reopening_requests',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'show_id')) continue;
            try {
                DB::table($table)->where('show_id', $from)->update(['show_id' => $to]);
            } catch (\Throwable) {
                $rows = DB::table($table)->where('show_id', $from)->get();
                foreach ($rows as $row) {
                    try {
                        DB::table($table)->where('id', $row->id)->update(['show_id' => $to]);
                    } catch (\Throwable) {
                        DB::table($table)->where('id', $row->id)->delete();
                    }
                }
            }
        }

        if (Schema::hasTable('streamer_log_entries') && Schema::hasColumn('streamer_log_entries', 'show_id')) {
            $canonicalExists = DB::table('streamer_log_entries')->where('show_id', $to)->exists();
            if ($canonicalExists) DB::table('streamer_log_entries')->where('show_id', $from)->delete();
            else DB::table('streamer_log_entries')->where('show_id', $from)->update(['show_id' => $to]);
        }
    }

    private function mergePivot(string $table, string $otherKey, int $from, int $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'show_id')) return;
        foreach (DB::table($table)->where('show_id', $from)->get() as $row) {
            $exists = DB::table($table)->where('show_id', $to)->where($otherKey, $row->{$otherKey})->exists();
            if (! $exists) {
                $data = (array) $row;
                unset($data['id']);
                $data['show_id'] = $to;
                DB::table($table)->insert($data);
            }
        }
        DB::table($table)->where('show_id', $from)->delete();
    }

    private function deleteShowGraph(Show $show): void
    {
        $show->delete();
    }
}
