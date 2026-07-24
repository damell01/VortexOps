<?php

namespace App\Services\AI;

use App\AI\DTOs\AiMessage;
use App\AI\Enums\AiTask;
use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use App\Filament\Pages\ProductInsights;
use App\Support\AdminModules;
use Illuminate\Support\Facades\Log;

/**
 * Turns the same numbers already on the Product Insights page into a 3-4
 * sentence plain-English inventory briefing — "your best margin is X, $Y is
 * dead stock, Z needs reordering" instead of scanning four tables. Reuses
 * ProductInsights's own (Livewire computed-property) row/KPI queries
 * directly rather than re-deriving the margin/dead-stock/reorder math a
 * second time, so the narrative can never drift from what the page shows.
 *
 * Same best-effort contract as OpsDigestService: returns null whenever the
 * AI or Inventory module is off, there's nothing worth summarizing, the
 * provider is unreachable, or generation fails for any reason.
 */
class ProductInsightsDigestService
{
    /** See OpsDigestService::GENERATION_TIMEOUT_SECONDS for why this is generous. */
    private const GENERATION_TIMEOUT_SECONDS = 90;

    private const TOP_N = 3;

    public function __construct(
        private readonly AiGateway $gateway,
        private readonly PromptLibrary $prompts,
    ) {}

    public function generate(): ?string
    {
        if (! AdminModules::isEnabled('ai') || ! AdminModules::isEnabled('inventory')) {
            return null;
        }

        try {
            $snapshot = $this->gatherSnapshot();

            if (! $this->hasSignal($snapshot)) {
                return null;
            }

            if (! $this->gateway->isHealthy()) {
                return null;
            }

            $response = $this->gateway->chat(
                AiTask::Reasoning,
                [AiMessage::user($this->prompts->productInsights($snapshot))],
                ['timeout' => self::GENERATION_TIMEOUT_SECONDS],
            );

            $text = trim($response->content);

            return $response->success && $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('ProductInsightsDigestService: generation failed — ' . $e->getMessage());

            return null;
        }
    }

    /** @return array<string,mixed> */
    private function gatherSnapshot(): array
    {
        $page = app(ProductInsights::class);
        $kpis = $page->getKpisProperty();

        $page->view = 'best_margin';
        $topMargin = $page->getRowsProperty()
            ->take(self::TOP_N)
            ->map(fn (array $r) => ['name' => $r['name'], 'margin' => $r['margin']])
            ->values()
            ->all();

        $page->view = 'dead_stock';
        $topDead = $page->getRowsProperty()
            ->take(self::TOP_N)
            ->map(fn (array $r) => ['name' => $r['name'], 'capital' => $r['capital'], 'days_since_sold' => $r['days_since_sold']])
            ->values()
            ->all();

        $page->view = 'reorder';
        $topReorder = $page->getRowsProperty()
            ->take(self::TOP_N)
            ->filter(fn (array $r) => $r['suggested_reorder_qty'] !== null)
            ->map(fn (array $r) => ['name' => $r['name'], 'suggested_qty' => $r['suggested_reorder_qty']])
            ->values()
            ->all();

        return array_merge($kpis, [
            'dead_days'   => ProductInsights::DEAD_DAYS,
            'top_margin'  => $topMargin,
            'top_dead'    => $topDead,
            'top_reorder' => $topReorder,
        ]);
    }

    /** @param array<string,mixed> $s */
    private function hasSignal(array $s): bool
    {
        return $s['active_skus'] > 0
            && ($s['inventory_value'] > 0
                || ! empty($s['top_margin'])
                || ! empty($s['top_dead'])
                || ! empty($s['top_reorder']));
    }
}
