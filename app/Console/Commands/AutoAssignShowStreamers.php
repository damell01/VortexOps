<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\AutomaticShowAssignment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoAssignShowStreamers extends Command
{
    protected $signature = 'shows:auto-assign-streamers {--since= : Earliest show date; defaults to this month} {--limit=1000}';
    protected $description = 'Create streamer accounts/profiles, save detected title aliases and assign unassigned shows automatically';

    public function handle(AutomaticShowAssignment $service): int
    {
        try { $since = $this->option('since') ? Carbon::createFromFormat('!Y-m-d', $this->option('since'))->toDateString() : today()->startOfMonth()->toDateString(); }
        catch (\Throwable) { $this->error('Use --since=YYYY-MM-DD.'); return self::FAILURE; }
        $lock = Cache::lock('shows:auto-assign-streamers', 600);
        if (! $lock->get()) { $this->info('Another automatic assignment run is active.'); return self::SUCCESS; }
        $counts = ['assigned' => 0, 'profiles_created' => 0, 'accounts_created' => 0, 'manual' => 0, 'unchanged' => 0, 'errors' => 0];
        try {
            $shows = Show::query()->whereDate('show_date', '>=', $since)->whereNotIn('status', ['cancelled', 'closed'])->whereDoesntHave('streamers')->orderByDesc('show_date')->limit(max(1, min(5000, (int) $this->option('limit'))))->get();
            foreach ($shows as $show) {
                try {
                    $result = $service->assign($show);
                    $counts[$result['status']]++;
                    $counts['profiles_created'] += (int) ($result['profile_created'] ?? false);
                    $counts['accounts_created'] += (int) ($result['account_created'] ?? false);
                } catch (\Throwable $e) {
                    $counts['errors']++;
                    report($e);
                    $this->error('Could not assign show #'.$show->id.'. See Laravel log.');
                }
            }
            $this->info('Automatic streamer assignment since '.$since.': '.json_encode($counts));
            return $counts['errors'] ? self::FAILURE : self::SUCCESS;
        } finally { $lock->release(); }
    }
}
