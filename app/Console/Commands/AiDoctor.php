<?php

namespace App\Console\Commands;

use App\AI\Contracts\PullsModels;
use App\AI\Enums\AiTask;
use App\AI\Services\AiGateway;
use App\AI\Services\ModelRouter;
use App\AI\Services\ToolRegistry;
use App\Models\Product;
use App\Support\AdminModules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-deploy sanity check for the AI stack. Confirms Ollama is reachable,
 * every configured task model is actually pulled, embeddings are populated,
 * and the ai queue isn't backed up — then prints a green/red checklist. Exit
 * code is non-zero when something critical is wrong, so it can gate a deploy.
 */
class AiDoctor extends Command
{
    protected $signature   = 'vortex:ai-doctor {--pull : Download any configured models that are missing}';
    protected $description = 'Check the AI stack: Ollama reachability, configured models, embeddings, and the ai queue';

    private bool $hasCritical = false;
    private bool $hasWarning  = false;

    public function handle(AiGateway $gateway, ModelRouter $router, ToolRegistry $tools): int
    {
        $this->line('');
        $this->line('  <fg=cyan;options=bold>VortexOps AI Doctor</>');
        $this->line('  <fg=gray>' . now()->toDateTimeString() . '</>');
        $this->line('');

        // ── Module + provider ────────────────────────────────────────────────
        AdminModules::isEnabled('ai')
            ? $this->ok('AI module', 'enabled')
            : $this->warn_('AI module', 'disabled — the assistant and embedding jobs are off (Settings → modules)');

        $provider = $gateway->provider();
        $online   = $gateway->isHealthy();

        if (! $online) {
            $this->critical('Ollama backend', "provider [{$provider->name()}] is NOT reachable — check OLLAMA_BASE_URL and that the container is up");
            $this->renderSummary();

            // No point probing models against a dead backend.
            return $this->hasCritical ? self::FAILURE : self::SUCCESS;
        }

        $this->ok('Ollama backend', "provider [{$provider->name()}] reachable");

        // ── Configured models actually pulled ────────────────────────────────
        $available = $provider->listModels();
        $this->line('  <fg=gray>  pulled models: ' . ($available ? implode(', ', $available) : '(none)') . '</>');
        $this->line('');

        // Chat / JSON / Embedding are required for the assistant + matching to
        // function; the rest degrade gracefully, so they only warn.
        $critical = [AiTask::Chat, AiTask::Json, AiTask::Embedding];

        // Which configured models aren't present (deduped — several tasks may
        // resolve to the same model).
        $missing = [];
        foreach (AiTask::cases() as $task) {
            $model = $router->modelFor($task);
            if (! $this->isPulled($model, $available)) {
                $missing[$model] = true;
            }
        }
        $missing = array_keys($missing);

        // --pull: fetch the missing ones over HTTP, then refresh availability so
        // the report below reflects what we just downloaded.
        if ($missing !== [] && $this->option('pull')) {
            $available = $this->pullMissing($provider, $missing, $available);
        }

        foreach (AiTask::cases() as $task) {
            $model    = $router->modelFor($task);
            $isPulled = $this->isPulled($model, $available);
            $label    = 'Model · ' . str_pad($task->label(), 16);

            if ($isPulled) {
                $this->ok($label, $model);
            } elseif (in_array($task, $critical, true)) {
                $this->critical($label, "\"{$model}\" is NOT pulled — run: ollama pull {$model} (or re-run with --pull)");
            } else {
                $this->warn_($label, "\"{$model}\" is NOT pulled (optional) — ollama pull {$model}");
            }
        }

        // ── Embedding coverage ───────────────────────────────────────────────
        $this->line('');
        $total    = Product::where('is_active', true)->count();
        $embedded = Product::where('is_active', true)->whereHas('embedding')->count();

        if ($total === 0) {
            $this->warn_('Embeddings', 'no active products to embed yet');
        } elseif ($embedded === $total) {
            $this->ok('Embeddings', "{$embedded}/{$total} active products embedded");
        } elseif ($embedded === 0) {
            $this->warn_('Embeddings', "0/{$total} embedded — run: php artisan ai:generate-embeddings");
        } else {
            $this->warn_('Embeddings', "{$embedded}/{$total} embedded — run ai:generate-embeddings to fill the gap");
        }

        // ── ai queue health ──────────────────────────────────────────────────
        $this->reportQueue();

        // ── Tools + generation settings ──────────────────────────────────────
        $this->line('');
        $toolNames = array_keys($tools->all());
        $this->ok('Tools registered', count($toolNames) . ' (' . implode(', ', $toolNames) . ')');
        $opts = $router->generationOptions(AiTask::Chat);
        $this->ok('Generation', sprintf(
            'temp %s · max_tokens %s · streaming %s',
            $opts['temperature'] ?? '—',
            $opts['max_tokens'] ?? '—',
            $router->streamingEnabled() ? 'on' : 'off',
        ));

        $this->renderSummary();

        return $this->hasCritical ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Download each missing model over the provider's HTTP API and return the
     * refreshed list of available models. No-ops (with a warning) when the
     * active provider can't pull.
     *
     * @param  string[] $missing
     * @param  string[] $available
     * @return string[]
     */
    private function pullMissing(object $provider, array $missing, array $available): array
    {
        if (! $provider instanceof PullsModels) {
            $this->warn_('Model pull', 'the active provider does not support pulling — install the models manually');
            return $available;
        }

        $this->line('  <fg=cyan>Pulling ' . count($missing) . ' missing model(s)…</>');

        foreach ($missing as $model) {
            $this->line("  <fg=cyan>↓</> {$model}");

            $lastStatus = '';
            $ok = $provider->pull($model, function (array $frame) use (&$lastStatus) {
                $status = (string) ($frame['status'] ?? ($frame['error'] ?? ''));
                // Dedupe on the phase name so progress bytes don't flood output.
                if ($status !== '' && $status !== $lastStatus) {
                    $this->line("    <fg=gray>{$status}</>");
                    $lastStatus = $status;
                }
            });

            $ok
                ? $this->line("    <fg=green>done</>")
                : $this->line("    <fg=red>failed — pull it manually: ollama pull {$model}</>");
        }

        $this->line('');

        // Ask the backend again so the report reflects the new state.
        return $provider->listModels();
    }

    /**
     * Whether a configured model name is among Ollama's pulled models, tolerant
     * of the ":latest" tag Ollama adds/omits (e.g. "nomic-embed-text" matches
     * "nomic-embed-text:latest").
     *
     * @param string[] $available
     */
    private function isPulled(string $model, array $available): bool
    {
        $normalize = fn (string $n) => str_ends_with($n, ':latest') ? substr($n, 0, -7) : $n;
        $target    = $normalize($model);

        foreach ($available as $name) {
            if ($name === $model || $normalize($name) === $target) {
                return true;
            }
        }

        return false;
    }

    private function reportQueue(): void
    {
        try {
            $failed = Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->count()
                : 0;

            // Pending count is only observable on the database queue driver.
            if (Schema::hasTable('jobs')) {
                $pendingAi = (int) DB::table('jobs')->where('queue', 'ai')->count();
                $pendingAi === 0
                    ? $this->ok('AI queue', 'no jobs pending on [ai]')
                    : $this->warn_('AI queue', "{$pendingAi} job(s) pending on [ai] — is `queue:work --queue=ai` running?");
            } else {
                $this->ok('AI queue', 'non-database driver — ensure the ai-worker is running');
            }

            $failed === 0
                ? $this->ok('Failed jobs', 'none')
                : $this->warn_('Failed jobs', "{$failed} failed — inspect with: php artisan queue:failed");
        } catch (\Throwable $e) {
            $this->warn_('AI queue', 'could not inspect: ' . $e->getMessage());
        }
    }

    // ── Output helpers ───────────────────────────────────────────────────────

    private function ok(string $label, string $detail): void
    {
        $this->line("  <fg=green>✓</> <options=bold>{$label}</>  <fg=gray>{$detail}</>");
    }

    private function warn_(string $label, string $detail): void
    {
        $this->hasWarning = true;
        $this->line("  <fg=yellow>!</> <options=bold>{$label}</>  <fg=yellow>{$detail}</>");
    }

    private function critical(string $label, string $detail): void
    {
        $this->hasCritical = true;
        $this->line("  <fg=red>✗</> <options=bold>{$label}</>  <fg=red>{$detail}</>");
    }

    private function renderSummary(): void
    {
        $this->line('');
        if ($this->hasCritical) {
            $this->line('  <bg=red;fg=white;options=bold> UNHEALTHY </> critical checks failed — the assistant will not work until fixed.');
        } elseif ($this->hasWarning) {
            $this->line('  <bg=yellow;fg=black;options=bold> DEGRADED </> core works; warnings above reduce functionality.');
        } else {
            $this->line('  <bg=green;fg=white;options=bold> HEALTHY </> the AI stack is fully operational.');
        }
        $this->line('');
    }
}
