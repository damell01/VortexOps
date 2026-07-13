<?php

namespace App\AI\Services;

use App\AI\Enums\AiTask;
use App\Models\Product;
use App\Support\AdminModules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single source of truth for "is the AI stack healthy?". Produces a
 * structured report — an overall status plus a list of labelled checks — that
 * both the vortex:ai-doctor command and the System Health page render, so the
 * CLI and the UI never drift apart.
 */
final class AiHealthReport
{
    public const OK       = 'ok';
    public const WARN     = 'warn';
    public const CRITICAL = 'critical';

    /** Tasks the assistant + matching can't work without. */
    private const CRITICAL_TASKS = [AiTask::Chat, AiTask::Json, AiTask::Embedding];

    public function __construct(
        private readonly AiGateway    $gateway,
        private readonly ModelRouter  $router,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * @return array{
     *   status: string, reachable: bool, provider: string,
     *   available_models: string[], missing_models: string[],
     *   checks: list<array{label:string, state:string, detail:string}>
     * }
     */
    public function generate(): array
    {
        $checks   = [];
        $provider = $this->gateway->provider();

        $checks[] = AdminModules::isEnabled('ai')
            ? $this->check('AI module', self::OK, 'enabled')
            : $this->check('AI module', self::WARN, 'disabled — the assistant and embedding jobs are off (Settings → modules)');

        // Backend reachability — everything else is meaningless if it's down.
        if (! $this->gateway->isHealthy()) {
            $checks[] = $this->check('Ollama backend', self::CRITICAL, "provider [{$provider->name()}] is NOT reachable — check OLLAMA_BASE_URL and that the container is up");

            return $this->assemble($checks, false, $provider->name(), [], []);
        }

        $checks[] = $this->check('Ollama backend', self::OK, "provider [{$provider->name()}] reachable");

        $available = $provider->listModels();
        $missing   = $this->modelChecks($available, $checks);

        $this->embeddingCheck($checks);
        $this->queueChecks($checks);
        $this->infoChecks($checks);

        return $this->assemble($checks, true, $provider->name(), $available, $missing);
    }

    /**
     * Whether a configured model is present among Ollama's pulled models,
     * tolerant of the ":latest" tag (nomic-embed-text ≈ nomic-embed-text:latest).
     *
     * @param string[] $available
     */
    public function isPulled(string $model, array $available): bool
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

    /**
     * Append a per-task model check for each AiTask and return the deduped list
     * of configured models that aren't pulled.
     *
     * @param  string[] $available
     * @param  list<array<string,string>> $checks
     * @return string[]
     */
    private function modelChecks(array $available, array &$checks): array
    {
        $missing = [];

        foreach (AiTask::cases() as $task) {
            $model    = $this->router->modelFor($task);
            $isPulled = $this->isPulled($model, $available);
            $label    = 'Model · ' . $task->label();

            if ($isPulled) {
                $checks[] = $this->check($label, self::OK, $model);
                continue;
            }

            $missing[$model] = true;

            $checks[] = in_array($task, self::CRITICAL_TASKS, true)
                ? $this->check($label, self::CRITICAL, "\"{$model}\" is NOT pulled — run: ollama pull {$model}")
                : $this->check($label, self::WARN, "\"{$model}\" is NOT pulled (optional) — ollama pull {$model}");
        }

        return array_keys($missing);
    }

    /** @param list<array<string,string>> $checks */
    private function embeddingCheck(array &$checks): void
    {
        $total    = Product::where('is_active', true)->count();
        $embedded = Product::where('is_active', true)->whereHas('embedding')->count();

        if ($total === 0) {
            $checks[] = $this->check('Embeddings', self::WARN, 'no active products to embed yet');
        } elseif ($embedded === $total) {
            $checks[] = $this->check('Embeddings', self::OK, "{$embedded}/{$total} active products embedded");
        } else {
            $checks[] = $this->check('Embeddings', self::WARN, "{$embedded}/{$total} embedded — run: php artisan ai:generate-embeddings");
        }
    }

    /** @param list<array<string,string>> $checks */
    private function queueChecks(array &$checks): void
    {
        try {
            if (Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->where('queue', 'ai')->count();
                $checks[] = $pending === 0
                    ? $this->check('AI queue', self::OK, 'no jobs pending on [ai]')
                    : $this->check('AI queue', self::WARN, "{$pending} job(s) pending on [ai] — is the ai worker running?");
            } else {
                $checks[] = $this->check('AI queue', self::OK, 'non-database driver — ensure the ai-worker is running');
            }

            $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
            $checks[] = $failed === 0
                ? $this->check('Failed jobs', self::OK, 'none')
                : $this->check('Failed jobs', self::WARN, "{$failed} failed — inspect with: php artisan queue:failed");
        } catch (\Throwable $e) {
            $checks[] = $this->check('AI queue', self::WARN, 'could not inspect: ' . $e->getMessage());
        }
    }

    /** @param list<array<string,string>> $checks */
    private function infoChecks(array &$checks): void
    {
        $toolNames = array_keys($this->tools->all());
        $checks[]  = $this->check('Tools registered', self::OK, count($toolNames) . ' (' . implode(', ', $toolNames) . ')');

        $opts     = $this->router->generationOptions(AiTask::Chat);
        $checks[] = $this->check('Generation', self::OK, sprintf(
            'temp %s · max_tokens %s · streaming %s',
            $opts['temperature'] ?? '—',
            $opts['max_tokens'] ?? '—',
            $this->router->streamingEnabled() ? 'on' : 'off',
        ));
    }

    /** @return array{label:string, state:string, detail:string} */
    private function check(string $label, string $state, string $detail): array
    {
        return ['label' => $label, 'state' => $state, 'detail' => $detail];
    }

    /**
     * @param  list<array{label:string,state:string,detail:string}> $checks
     * @param  string[] $available
     * @param  string[] $missing
     */
    private function assemble(array $checks, bool $reachable, string $provider, array $available, array $missing): array
    {
        $states = array_column($checks, 'state');
        $status = in_array(self::CRITICAL, $states, true) ? 'unhealthy'
            : (in_array(self::WARN, $states, true) ? 'degraded' : 'healthy');

        return [
            'status'           => $status,
            'reachable'        => $reachable,
            'provider'         => $provider,
            'available_models' => $available,
            'missing_models'   => $missing,
            'checks'           => $checks,
        ];
    }
}
