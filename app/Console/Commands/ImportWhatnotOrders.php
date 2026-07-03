<?php

namespace App\Console\Commands;

use App\Jobs\RunShowAiMappingJob;
use App\Models\AiTask;
use App\Models\Show;
use App\Models\User;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportWhatnotOrders extends Command
{
    protected $signature = 'whatnot:import-orders
                            {--show=    : Show ID to import orders for (omit for all shows with a detail_url)}
                            {--recent   : Only shows from the last 30 days}
                            {--new-only : Only shows that have a detail_url but no orders imported yet}
                            {--debug    : Save Playwright screenshots to /tmp for debugging selectors}';

    protected $description = 'Scrape order/lot data for completed Whatnot shows and store buyer + item details';

    public function handle(WhatnotScraper $scraper): int
    {
        $debug = (bool) $this->option('debug');

        $query = Show::whereNotNull('detail_url');

        if ($showId = $this->option('show')) {
            $query->where('id', $showId);
        }

        if ($this->option('new-only')) {
            $query->whereDoesntHave('orders');
        }

        if ($this->option('recent')) {
            $query->where('show_date', '>=', now()->subDays(30)->toDateString());
        }

        $shows = $query->orderByDesc('show_date')->get();

        if ($shows->isEmpty()) {
            $this->warn('No shows found with a detail_url. Run `php artisan whatnot:import` first to populate show URLs.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($shows as $show) {
            $this->line("Importing orders for: {$show->title} ({$show->show_date?->format('Y-m-d')})");

            try {
                $result = $scraper->importShowOrders($show, $debug);
                $this->info("  ✓ {$result['created']} created, {$result['skipped']} skipped");
                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];

                if ($result['created'] > 0) {
                    $this->advanceShowAndQueueMapping($show);
                }
            } catch (\RuntimeException $e) {
                $this->error("  ✗ {$e->getMessage()}");

                if (str_contains($e->getMessage(), 'selectors')) {
                    $this->line('    Re-run with --debug to capture screenshots and update SELECTORS in scripts/whatnot-scraper.cjs');
                }
            }
        }

        $this->info('');
        $this->info("Done — {$totalCreated} orders created, {$totalSkipped} skipped across {$shows->count()} shows.");

        return self::SUCCESS;
    }

    private function advanceShowAndQueueMapping(Show $show): void
    {
        $show->refresh();

        // Only advance shows still in draft — higher statuses mean work is already underway
        if ($show->status !== 'draft') {
            return;
        }

        $show->update(['status' => 'pending_review']);
        $this->line("  → Transitioned to pending_review");

        AdminModules::flushMemo();

        if (! AdminModules::isEnabled('ai')) {
            return;
        }

        // Guard against duplicate tasks if the scheduler fires twice in quick succession
        $alreadyQueued = AiTask::where('taskable_type', Show::class)
            ->where('taskable_id', $show->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        $owner = User::where('email', config('app.owner_email'))->first();

        $task = AiTask::create([
            'type'          => 'show_ai_mapping',
            'status'        => 'pending',
            'taskable_type' => Show::class,
            'taskable_id'   => $show->id,
            'triggered_by'  => $owner?->id,
            'input'         => ['show_id' => $show->id, 'show_title' => $show->title],
        ]);

        RunShowAiMappingJob::dispatch($show->id, $task->id)->onQueue('ai');

        $this->line("  → AI mapping queued (task #{$task->id})");

        Log::info('ImportWhatnotOrders: AI mapping queued', [
            'show_id'     => $show->id,
            'ai_task_id'  => $task->id,
        ]);
    }
}
