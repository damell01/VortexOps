<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;

/**
 * "Where's the payroll at?" — status of the most recent weekly payout batch
 * (draft / finalized / submitted / paid) and its total.
 */
final class PayoutBatchStatusTool implements Tool
{
    public function name(): string
    {
        return 'payout.batch_status';
    }

    public function description(): string
    {
        return 'Report the status and total of the most recent weekly payout batch (draft, finalized, submitted to ADP, or paid). Use for "where is payroll" or "is this week paid".';
    }

    public function parameters(): array
    {
        return [];
    }

    public function authorize(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function run(array $arguments): ToolResult
    {
        $batch = WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->latest('week_start')
            ->first();

        if (! $batch) {
            return ToolResult::error('There are no payout batches yet.');
        }

        $status = WeeklyPayoutBatch::statusLabels()[$batch->status] ?? $batch->status;
        $week   = optional($batch->week_start)->toDateString() . ' – ' . optional($batch->week_end)->toDateString();
        $total  = number_format((float) $batch->total_payout, 2);

        return ToolResult::ok(
            "Latest payout batch ({$week}) is {$status}: {$batch->payouts_count} payout(s) totaling \${$total}.",
            [
                'status'       => $batch->status,
                'status_label' => $status,
                'week_start'   => optional($batch->week_start)->toDateString(),
                'week_end'     => optional($batch->week_end)->toDateString(),
                'payouts'      => $batch->payouts_count,
                'total_payout' => round((float) $batch->total_payout, 2),
            ],
        );
    }
}
