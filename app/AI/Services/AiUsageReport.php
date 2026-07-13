<?php

namespace App\AI\Services;

use App\Models\AiInteraction;
use Illuminate\Support\Facades\DB;

/**
 * Read-model over the ai_interactions telemetry. Turns the raw call log into the
 * numbers the monitoring dashboard shows — totals, success rate, latency by task
 * and model, and recent failures — all as grouped SQL so it scales.
 */
final class AiUsageReport
{
    /**
     * Headline numbers for the last $days days.
     *
     * @return array{calls:int, success:int, failed:int, success_rate:float, avg_latency:int}
     */
    public function summary(int $days = 7): array
    {
        $row = AiInteraction::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok_count')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->first();

        $calls   = (int) ($row->calls ?? 0);
        $success = (int) ($row->ok_count ?? 0);

        return [
            'calls'        => $calls,
            'success'      => $success,
            'failed'       => $calls - $success,
            'success_rate' => $calls > 0 ? round($success / $calls * 100, 1) : 0.0,
            'avg_latency'  => (int) round((float) ($row->avg_latency ?? 0)),
        ];
    }

    /**
     * Per-task breakdown: calls, success rate, average latency.
     *
     * @return list<array{task:string, calls:int, success_rate:float, avg_latency:int}>
     */
    public function byTask(int $days = 7): array
    {
        return $this->groupBy('task', $days);
    }

    /**
     * Per-model breakdown.
     *
     * @return list<array{model:string, calls:int, success_rate:float, avg_latency:int}>
     */
    public function byModel(int $days = 7): array
    {
        return $this->groupBy('model', $days);
    }

    /**
     * The most recent failures for troubleshooting.
     *
     * @return list<array{task:string, model:string, error:string, created_at:?string}>
     */
    public function recentFailures(int $limit = 10): array
    {
        return AiInteraction::query()
            ->where('success', false)
            ->latest('created_at')
            ->limit($limit)
            ->get(['task', 'model', 'error', 'created_at'])
            ->map(fn (AiInteraction $i) => [
                'task'       => $i->task,
                'model'      => $i->model,
                'error'      => (string) $i->error,
                'created_at' => optional($i->created_at)->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return list<array{task?:string, model?:string, calls:int, success_rate:float, avg_latency:int}>
     */
    private function groupBy(string $column, int $days): array
    {
        return AiInteraction::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy($column)
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw("{$column} as label")
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as ok_count')
            ->selectRaw('AVG(latency_ms) as avg_latency')
            ->get()
            ->map(fn ($r) => [
                $column        => (string) $r->label,
                'calls'        => (int) $r->calls,
                'success_rate' => $r->calls > 0 ? round($r->ok_count / $r->calls * 100, 1) : 0.0,
                'avg_latency'  => (int) round((float) $r->avg_latency),
            ])
            ->all();
    }
}
