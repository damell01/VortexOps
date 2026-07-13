<?php

namespace App\Console\Commands;

use App\AI\Contracts\PullsModels;
use App\AI\Services\AiGateway;
use App\AI\Services\AiHealthReport;
use Illuminate\Console\Command;

/**
 * Post-deploy sanity check for the AI stack. Renders the shared AiHealthReport
 * as a green/red checklist and, with --pull, downloads any missing models over
 * HTTP before reporting. Exit code is non-zero on a critical failure so it can
 * gate a deploy.
 */
class AiDoctor extends Command
{
    protected $signature   = 'vortex:ai-doctor {--pull : Download any configured models that are missing}';
    protected $description = 'Check the AI stack: Ollama reachability, configured models, embeddings, and the ai queue';

    public function handle(AiGateway $gateway, AiHealthReport $health): int
    {
        $this->line('');
        $this->line('  <fg=cyan;options=bold>VortexOps AI Doctor</>');
        $this->line('  <fg=gray>' . now()->toDateTimeString() . '</>');
        $this->line('');

        $report = $health->generate();

        // --pull: fetch missing models over HTTP, then re-run the report so it
        // reflects what we just downloaded.
        if ($report['reachable'] && $report['missing_models'] !== [] && $this->option('pull')) {
            $this->pullMissing($gateway, $report['missing_models']);
            $report = $health->generate();
        }

        if ($report['available_models'] !== [] || $report['reachable']) {
            $this->line('  <fg=gray>  pulled models: ' . ($report['available_models'] ? implode(', ', $report['available_models']) : '(none)') . '</>');
            $this->line('');
        }

        foreach ($report['checks'] as $check) {
            $this->renderCheck($check);
        }

        $this->renderSummary($report['status']);

        return $report['status'] === 'unhealthy' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Download each missing model over the provider's HTTP API.
     *
     * @param string[] $missing
     */
    private function pullMissing(AiGateway $gateway, array $missing): void
    {
        $provider = $gateway->provider();

        if (! $provider instanceof PullsModels) {
            $this->warn('  The active provider does not support pulling — install the models manually.');
            return;
        }

        $this->line('  <fg=cyan>Pulling ' . count($missing) . ' missing model(s)…</>');

        foreach ($missing as $model) {
            $this->line("  <fg=cyan>↓</> {$model}");

            $lastStatus = '';
            $ok = $provider->pull($model, function (array $frame) use (&$lastStatus) {
                $status = (string) ($frame['status'] ?? ($frame['error'] ?? ''));
                if ($status !== '' && $status !== $lastStatus) {
                    $this->line("    <fg=gray>{$status}</>");
                    $lastStatus = $status;
                }
            });

            $ok
                ? $this->line('    <fg=green>done</>')
                : $this->line("    <fg=red>failed — pull it manually: ollama pull {$model}</>");
        }

        $this->line('');
    }

    /** @param array{label:string, state:string, detail:string} $check */
    private function renderCheck(array $check): void
    {
        [$glyph, $color] = match ($check['state']) {
            AiHealthReport::OK       => ['✓', 'green'],
            AiHealthReport::WARN     => ['!', 'yellow'],
            default                  => ['✗', 'red'],
        };

        $detailColor = $check['state'] === AiHealthReport::OK ? 'gray' : $color;

        $this->line("  <fg={$color}>{$glyph}</> <options=bold>{$check['label']}</>  <fg={$detailColor}>{$check['detail']}</>");
    }

    private function renderSummary(string $status): void
    {
        $this->line('');
        match ($status) {
            'unhealthy' => $this->line('  <bg=red;fg=white;options=bold> UNHEALTHY </> critical checks failed — the assistant will not work until fixed.'),
            'degraded'  => $this->line('  <bg=yellow;fg=black;options=bold> DEGRADED </> core works; warnings above reduce functionality.'),
            default     => $this->line('  <bg=green;fg=white;options=bold> HEALTHY </> the AI stack is fully operational.'),
        };
        $this->line('');
    }
}
