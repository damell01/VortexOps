<?php

namespace App\Console\Commands;

use App\Services\AI\Ops\AiOpsDispatcher;
use Illuminate\Console\Command;

class QueueAiOpsInsights extends Command
{
    protected $signature = 'ai:ops
        {scope=operations : operations|weekly|inventory|payroll|streamers|exceptions|cleanup|show}
        {--source-id= : Optional show/source ID for scoped work}
        {--force : Queue even if equivalent work is already pending}';

    protected $description = 'Queue low-resource VortexOps AI operations analysis on the dedicated ai worker';

    public function handle(AiOpsDispatcher $dispatcher): int
    {
        $scope = strtolower((string) $this->argument('scope'));
        $allowed = ['operations', 'weekly', 'inventory', 'payroll', 'streamers', 'exceptions', 'cleanup', 'show'];

        if (! in_array($scope, $allowed, true)) {
            $this->error('Unknown scope. Allowed: ' . implode(', ', $allowed));
            return self::INVALID;
        }

        $sourceId = $this->option('source-id');
        $sourceId = is_numeric($sourceId) ? (int) $sourceId : null;

        if ($scope === 'show' && ! $sourceId) {
            $this->error('--source-id is required for show scope.');
            return self::INVALID;
        }

        $task = $dispatcher->dispatch(
            scope: $scope,
            sourceId: $sourceId,
            triggeredBy: null,
            force: (bool) $this->option('force'),
        );

        if (! $task) {
            $this->warn('AI operations are disabled. Nothing queued.');
            return self::SUCCESS;
        }

        $this->info("Queued {$scope} AI operations task #{$task->id} on the ai queue.");
        return self::SUCCESS;
    }
}
