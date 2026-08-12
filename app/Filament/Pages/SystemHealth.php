<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Filament\Concerns\HasAdminNavVisibility;

class SystemHealth extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'System Health';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-heart';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.system-health';
    }

    public function getSubheading(): ?string
    {
        return 'Diagnostic checks — queue, database, storage, and scraper connectivity — for confirming the app is actually healthy, not just running.';
    }

    // ── Metrics ───────────────────────────────────────────────────────────────

    public function getDbStatusProperty(): array
    {
        try {
            DB::select('SELECT 1');
            return ['ok' => true, 'label' => 'Connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'label' => $e->getMessage()];
        }
    }

    public function getQueueStatusProperty(): array
    {
        try {
            $defaultPending = DB::table('jobs')->where('queue', 'default')->count();
            $aiPending      = DB::table('jobs')->where('queue', 'ai')->count();
            $failed         = DB::table('failed_jobs')->count();

            $reserved = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '>', now()->subMinutes(10)->getTimestamp())
                ->count();

            return [
                'ok'             => $failed === 0,
                'default_pending' => $defaultPending,
                'ai_pending'      => $aiPending,
                'failed'          => $failed,
                'active'          => $reserved,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'default_pending' => 0, 'ai_pending' => 0, 'failed' => 0, 'active' => 0];
        }
    }

    public function getFailedJobsProperty(): array
    {
        try {
            return DB::table('failed_jobs')
                ->latest('failed_at')
                ->limit(10)
                ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
                ->map(function ($row) {
                    $payload = json_decode($row->payload, true);
                    $job     = $payload['displayName'] ?? $payload['job'] ?? 'Unknown';
                    $job     = class_basename($job);
                    return [
                        'id'         => $row->id,
                        'job'        => $job,
                        'queue'      => $row->queue,
                        'failed_at'  => $row->failed_at,
                        'exception'  => str($row->exception)->limit(200)->toString(),
                    ];
                })
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getOllamaStatusProperty(): array
    {
        try {
            $url      = rtrim(Setting::get('ollama_base_url') ?: config('services.ollama.url', 'http://localhost:11434'), '/');
            $response = Http::timeout(3)->get("{$url}/api/tags");

            if ($response->successful()) {
                $models = collect($response->json('models', []))->pluck('name')->all();
                return ['ok' => true, 'url' => $url, 'models' => $models, 'label' => 'Online'];
            }

            return ['ok' => false, 'url' => $url, 'models' => [], 'label' => "HTTP {$response->status()}"];
        } catch (\Throwable $e) {
            $url = rtrim(Setting::get('ollama_base_url') ?: config('services.ollama.url', 'http://localhost:11434'), '/');
            return ['ok' => false, 'url' => $url, 'models' => [], 'label' => $e->getMessage()];
        }
    }

    /**
     * The full AI-stack checklist (models, embeddings, queue, tools) — the same
     * report the vortex:ai-doctor command renders, surfaced in the UI.
     *
     * @return array{status:string, reachable:bool, provider:string, available_models:array, missing_models:array, checks:array}
     */
    public function getAiHealthProperty(): array
    {
        try {
            return app(\App\AI\Services\AiHealthReport::class)->generate();
        } catch (\Throwable $e) {
            return [
                'status'           => 'unhealthy',
                'reachable'        => false,
                'provider'         => 'unknown',
                'available_models' => [],
                'missing_models'   => [],
                'checks'           => [[
                    'label'  => 'AI stack',
                    'state'  => \App\AI\Services\AiHealthReport::CRITICAL,
                    'detail' => 'Could not resolve the AI provider — ' . $e->getMessage(),
                ]],
            ];
        }
    }

    public function getSchedulerStatusProperty(): array
    {
        try {
            $lastHeartbeat = Setting::get('scheduler_last_heartbeat');
            if (! $lastHeartbeat) {
                return ['ok' => null, 'label' => 'No heartbeat recorded yet'];
            }
            $ts   = \Carbon\Carbon::parse($lastHeartbeat);
            $late = $ts->lt(now()->subMinutes(3));
            return [
                'ok'    => ! $late,
                'label' => $late
                    ? "Last run {$ts->diffForHumans()} — scheduler may be down"
                    : "Running — last heartbeat {$ts->diffForHumans()}",
            ];
        } catch (\Throwable) {
            return ['ok' => null, 'label' => 'Unable to check'];
        }
    }

    public function getWorkerStatusProperty(): array
    {
        try {
            $lastHeartbeat = Setting::get('worker_last_heartbeat');
            if (! $lastHeartbeat) {
                return ['ok' => null, 'label' => 'No heartbeat yet — start a queue worker'];
            }
            $ts   = \Carbon\Carbon::parse($lastHeartbeat);
            // Heartbeat job is enqueued every minute; allow 5 min of slack for a
            // busy worker before calling it down.
            $late = $ts->lt(now()->subMinutes(5));
            return [
                'ok'    => ! $late,
                'label' => $late
                    ? "Last drained {$ts->diffForHumans()} — queue worker may be down"
                    : "Draining jobs — last heartbeat {$ts->diffForHumans()}",
            ];
        } catch (\Throwable) {
            return ['ok' => null, 'label' => 'Unable to check'];
        }
    }

    public function getStorageStatusProperty(): array
    {
        try {
            $path  = storage_path();
            $free  = disk_free_space($path);
            $total = disk_total_space($path);
            $used  = $total - $free;
            $pct   = $total > 0 ? round($used / $total * 100, 1) : 0;
            return [
                'ok'    => $pct < 90,
                'used'  => $this->humanBytes($used),
                'free'  => $this->humanBytes($free),
                'total' => $this->humanBytes($total),
                'pct'   => $pct,
            ];
        } catch (\Throwable) {
            return ['ok' => null, 'used' => '?', 'free' => '?', 'total' => '?', 'pct' => 0];
        }
    }

    public function clearFailedJobs(): void
    {
        try {
            DB::table('failed_jobs')->truncate();
            Notification::make()->title('Failed jobs cleared')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    public function clearSeedData(): void
    {
        if (! auth()->user()?->isOwner()) {
            Notification::make()->title('Not authorized')->danger()->send();
            return;
        }

        try {
            DB::transaction(function () {
                // Operational data — order respects FK constraints
                DB::table('inventory_cases')->delete();
                DB::table('receiving_sessions')->delete();
                DB::table('pallet_lines')->delete();
                DB::table('pallets')->delete();
                DB::table('inventory_movements')->delete();
                DB::table('inventory_stock')->delete();
                DB::table('inventory_lots')->delete();
                DB::table('inventory_locations')->delete();
                DB::table('product_identities')->delete();
                DB::table('products')->delete();
                DB::table('deduction_request_lines')->delete();
                DB::table('deduction_requests')->delete();
                DB::table('show_streamer')->delete();
                DB::table('show_ingestion_logs')->delete();
                DB::table('whatnot_show_orders')->delete();
                DB::table('payouts')->delete();
                DB::table('weekly_payout_batches')->delete();
                DB::table('shows')->delete();
                DB::table('streamer_loans')->delete();
                DB::table('streamers')->delete();
                DB::table('vendors')->delete();
                DB::table('time_entries')->delete();
                DB::table('project_milestones')->delete();
                DB::table('project_updates')->delete();
                DB::table('projects')->delete();
                DB::table('ai_tasks')->delete();
                DB::table('activity_log')->delete();
                DB::table('feedback_ticket_comments')->delete();
                DB::table('feedback_tickets')->delete();
                // Keep: users, settings, whatnot_channels, roles/permissions, cache, jobs
            });

            Notification::make()
                ->title('Demo data cleared')
                ->body('All seed/sample data has been removed. Real data entry can begin.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
    }

    private function humanBytes(float $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' PB';
    }
}
