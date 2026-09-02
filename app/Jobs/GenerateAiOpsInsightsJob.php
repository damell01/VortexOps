<?php

namespace App\Jobs;

use App\AI\Services\AiGateway;
use App\Models\AiInsight;
use App\Models\AiTask;
use App\Services\AI\Ops\AiOpsSnapshotBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Background-only AI operations worker.
 *
 * Every expensive operation happens here on the dedicated `ai` queue. The UI
 * only reads persisted AiTask/AiInsight rows, so a slow/cold local model cannot
 * make normal Filament page requests slow.
 */
class GenerateAiOpsInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 240;
    public int $tries = 1;

    public function __construct(
        public readonly int $aiTaskId,
        public readonly string $scope,
        public readonly ?int $sourceId = null,
    ) {}

    public function handle(AiOpsSnapshotBuilder $builder, AiGateway $ai): void
    {
        $task = AiTask::find($this->aiTaskId);
        if (! $task) {
            return;
        }

        $task->markProcessing();

        try {
            $snapshot = $builder->build($this->scope, $this->sourceId);
            $deterministic = $this->deterministicInsights($snapshot);
            $stored = 0;

            foreach ($deterministic as $insight) {
                $this->storeInsight($insight, $task);
                $stored++;
            }

            $aiOutput = null;
            $aiAvailable = false;

            // Health is checked only inside the queue worker. If Ollama is off,
            // deterministic alerts still persist and the rest of VortexOps is
            // unaffected.
            if (config('ai.ops.use_llm', true) && $ai->isHealthy()) {
                $aiAvailable = true;
                $aiOutput = $this->generateNarrative($ai, $snapshot);

                if (is_array($aiOutput)) {
                    if (filled($aiOutput['summary'] ?? null)) {
                        $this->storeInsight([
                            'category' => $this->categoryForScope($this->scope),
                            'severity' => 'info',
                            'title' => $this->summaryTitle($this->scope),
                            'summary' => (string) $aiOutput['summary'],
                            'details' => ['kind' => 'ai_summary', 'scope' => $this->scope],
                            'source_type' => $this->scope === 'show' ? 'show' : 'ops_scope',
                            'source_id' => $this->sourceId,
                        ], $task);
                        $stored++;
                    }

                    foreach (array_slice($aiOutput['insights'] ?? [], 0, 8) as $row) {
                        if (! is_array($row) || blank($row['title'] ?? null) || blank($row['summary'] ?? null)) {
                            continue;
                        }

                        $this->storeInsight([
                            'category' => $this->cleanCategory($row['category'] ?? $this->categoryForScope($this->scope)),
                            'severity' => $this->cleanSeverity($row['severity'] ?? 'info'),
                            'title' => Str::limit(strip_tags((string) $row['title']), 240, ''),
                            'summary' => Str::limit(strip_tags((string) $row['summary']), 1800, ''),
                            'details' => ['kind' => 'ai_interpretation', 'scope' => $this->scope],
                            'source_type' => $this->scope === 'show' ? 'show' : 'ops_scope',
                            'source_id' => $this->sourceId,
                        ], $task);
                        $stored++;
                    }
                }
            }

            $task->markCompleted([
                'scope' => $this->scope,
                'source_id' => $this->sourceId,
                'ai_available' => $aiAvailable,
                'insights_stored' => $stored,
                'snapshot' => $snapshot,
                'narrative' => $aiOutput,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateAiOpsInsightsJob failed', [
                'task_id' => $this->aiTaskId,
                'scope' => $this->scope,
                'source_id' => $this->sourceId,
                'error' => $e->getMessage(),
            ]);
            $task->markFailed($e->getMessage());
        }
    }

    /** @return array<string,mixed>|null */
    private function generateNarrative(AiGateway $ai, array $snapshot): ?array
    {
        $payload = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $maxChars = max(3000, (int) config('ai.ops.max_payload_chars', 12000));
        $payload = Str::limit((string) $payload, $maxChars, '');

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an operations analyst inside VortexOps. Use ONLY the supplied facts. Do not invent totals or business states. Never recommend changing stock, payroll, fulfillment, or financial records automatically. Return concise JSON with keys summary and insights. insights is an array of at most 5 objects with category, severity (info|low|medium|high), title, summary. Focus on useful exceptions, trends, cleanup suggestions, and things a human should review.',
            ],
            [
                'role' => 'user',
                'content' => "Scope: {$this->scope}\nDeterministic VortexOps facts:\n{$payload}",
            ],
        ];

        return $ai->json($messages, [
            'temperature' => 0.15,
            'max_tokens' => (int) config('ai.ops.max_tokens', 600),
            'context_length' => (int) config('ai.ops.context_length', 2048),
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function deterministicInsights(array $snapshot): array
    {
        $rows = [];
        $scope = $snapshot['scope'] ?? $this->scope;

        $inventory = $scope === 'inventory' ? $snapshot : ($snapshot['inventory'] ?? null);
        if (is_array($inventory)) {
            if (($inventory['negative_stock_count'] ?? 0) > 0) {
                $rows[] = $this->row('inventory', 'high', 'Negative inventory needs review',
                    ($inventory['negative_stock_count']) . ' stock row(s) are below zero. Review the underlying movements before making any adjustment.',
                    ['items' => $inventory['negative_stock'] ?? []]);
            }
            if (($inventory['low_stock_count'] ?? 0) > 0) {
                $rows[] = $this->row('inventory', 'medium', 'Low-stock items need attention',
                    ($inventory['low_stock_count']) . ' item(s) are at or below their reorder level.',
                    ['items' => $inventory['low_stock'] ?? []]);
            }
            if (($inventory['missing_cost'] ?? 0) > 0) {
                $rows[] = $this->row('inventory', 'low', 'Products are missing cost data',
                    ($inventory['missing_cost']) . ' active product(s) have no usable unit or average cost.', []);
            }
        }

        $exceptions = $scope === 'exceptions' ? $snapshot : ($snapshot['exceptions'] ?? null);
        if (is_array($exceptions)) {
            if (($exceptions['failed_imports_7d'] ?? 0) > 0) {
                $rows[] = $this->row('imports', 'high', 'Recent Whatnot imports failed',
                    ($exceptions['failed_imports_7d']) . ' ingestion failure(s) were recorded in the last 7 days.',
                    ['failures' => $exceptions['failed_imports'] ?? []]);
            }
            if (($exceptions['stale_pipeline_shows'] ?? 0) > 0) {
                $rows[] = $this->row('shows', 'medium', 'Shows are stuck in the workflow',
                    ($exceptions['stale_pipeline_shows']) . ' show(s) have remained outside reconciled/paid status for more than 7 days.', []);
            }
            if (($exceptions['channel_attribution_suspect'] ?? 0) > 0) {
                $rows[] = $this->row('shows', 'medium', 'Channel attribution needs review',
                    ($exceptions['channel_attribution_suspect']) . ' show(s) are flagged with suspect channel attribution.', []);
            }
        }

        $payroll = $scope === 'payroll' ? $snapshot : ($snapshot['payroll'] ?? null);
        if (is_array($payroll)) {
            if (($payroll['unbatched_drafts'] ?? 0) > 0) {
                $rows[] = $this->row('payroll', 'medium', 'Draft payouts are not in a Pay Run',
                    ($payroll['unbatched_drafts']) . ' draft payout(s), totaling $' . number_format((float) ($payroll['unbatched_amount'] ?? 0), 2) . ', are not attached to a weekly Pay Run.', []);
            }
            if (($payroll['missing_calculation'] ?? 0) > 0) {
                $rows[] = $this->row('payroll', 'high', 'Payout calculations are missing',
                    ($payroll['missing_calculation']) . ' draft payout(s) do not have a calculated payout amount.', []);
            }
        }

        if ($scope === 'cleanup') {
            if (($snapshot['duplicate_group_count'] ?? 0) > 0) {
                $rows[] = $this->row('cleanup', 'medium', 'Possible duplicate products found',
                    ($snapshot['duplicate_group_count']) . ' exact-normalized product-name group(s) should be reviewed before any merge.',
                    ['groups' => $snapshot['duplicate_groups'] ?? []]);
            }
            if (($snapshot['normalization_candidate_count'] ?? 0) > 0) {
                $rows[] = $this->row('cleanup', 'low', 'Product names can be normalized',
                    ($snapshot['normalization_candidate_count']) . ' product name(s) contain spacing/formatting inconsistencies.',
                    ['items' => $snapshot['normalization_candidates'] ?? []]);
            }
            if (($snapshot['missing_category_count'] ?? 0) > 0) {
                $rows[] = $this->row('cleanup', 'low', 'Products need category suggestions',
                    ($snapshot['missing_category_count']) . ' active product(s) have no category. AI can suggest categories for human review.',
                    ['sample' => $snapshot['category_sample'] ?? []]);
            }
        }

        if ($scope === 'show' && isset($snapshot['show']) && is_array($snapshot['show'])) {
            $show = $snapshot['show'];
            $pnl = $show['pnl'] ?? [];
            $marginPct = (float) ($pnl['margin_pct'] ?? 0);
            if ($marginPct < 0) {
                $rows[] = $this->row('shows', 'high', 'Show has a negative net margin',
                    ($show['title'] ?? 'Show') . ' currently has a ' . number_format($marginPct, 1) . '% net margin based on deterministic VortexOps P&L.',
                    ['show_id' => $show['id'] ?? $this->sourceId, 'pnl' => $pnl], 'show', $show['id'] ?? $this->sourceId);
            }
            if (! empty($show['channel_suspect'])) {
                $rows[] = $this->row('shows', 'medium', 'Show channel assignment is suspect',
                    ($show['title'] ?? 'Show') . ' is flagged for channel-attribution review.',
                    ['show_id' => $show['id'] ?? $this->sourceId], 'show', $show['id'] ?? $this->sourceId);
            }
        }

        return array_slice($rows, 0, 12);
    }

    /** @return array<string,mixed> */
    private function row(string $category, string $severity, string $title, string $summary, array $details = [], ?string $sourceType = null, ?int $sourceId = null): array
    {
        return compact('category', 'severity', 'title', 'summary', 'details', 'sourceType', 'sourceId');
    }

    /** @param array<string,mixed> $data */
    private function storeInsight(array $data, AiTask $task): void
    {
        $sourceType = $data['source_type'] ?? $data['sourceType'] ?? ($this->scope === 'show' ? 'show' : 'ops_scope');
        $sourceId = $data['source_id'] ?? $data['sourceId'] ?? $this->sourceId;
        $title = Str::limit((string) ($data['title'] ?? 'AI Insight'), 240, '');
        $summary = Str::limit((string) ($data['summary'] ?? ''), 1800, '');

        // De-dupe equivalent open insights for 24h so a daily/hourly job doesn't
        // flood the dashboard with the same warning.
        $existing = AiInsight::query()
            ->where('category', $this->cleanCategory($data['category'] ?? 'operations'))
            ->where('title', $title)
            ->where('status', AiInsight::STATUS_OPEN)
            ->where('created_at', '>=', now()->subDay())
            ->when($sourceId !== null, fn ($q) => $q->where('source_type', $sourceType)->where('source_id', $sourceId))
            ->first();

        if ($existing) {
            $existing->update([
                'summary' => $summary,
                'details' => $data['details'] ?? [],
                'ai_task_id' => $task->id,
                'generated_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);
            return;
        }

        AiInsight::create([
            'category' => $this->cleanCategory($data['category'] ?? 'operations'),
            'severity' => $this->cleanSeverity($data['severity'] ?? 'info'),
            'title' => $title,
            'summary' => $summary,
            'details' => $data['details'] ?? [],
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'ai_task_id' => $task->id,
            'status' => AiInsight::STATUS_OPEN,
            'generated_at' => now(),
            'expires_at' => now()->addDays(14),
        ]);
    }

    private function cleanSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));
        return in_array($severity, ['info', 'low', 'medium', 'high'], true) ? $severity : 'info';
    }

    private function cleanCategory(string $category): string
    {
        $category = Str::slug(strtolower($category), '_');
        return Str::limit($category ?: 'operations', 40, '');
    }

    private function categoryForScope(string $scope): string
    {
        return match ($scope) {
            'show' => 'shows',
            'streamers' => 'streamers',
            'cleanup' => 'cleanup',
            'exceptions' => 'exceptions',
            'weekly' => 'management',
            default => $this->cleanCategory($scope),
        };
    }

    private function summaryTitle(string $scope): string
    {
        return match ($scope) {
            'weekly' => 'Weekly management summary',
            'show' => 'Show summary',
            'inventory' => 'Inventory summary',
            'payroll' => 'Payroll review summary',
            'streamers' => 'Streamer performance summary',
            'cleanup' => 'Data cleanup summary',
            'exceptions' => 'Exceptions summary',
            default => 'Operations summary',
        };
    }
}
