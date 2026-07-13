<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\DeductionRequest;
use App\Models\User;

/**
 * "What COGS is waiting on me?" — deduction requests still pending approval,
 * with the total cost-of-goods they represent.
 */
final class PendingDeductionsTool implements Tool
{
    public function name(): string
    {
        return 'deduction.pending';
    }

    public function description(): string
    {
        return 'Summarize deduction requests still pending approval and the total COGS they represent. Use for "what deductions need approval" or "how much COGS is waiting".';
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
        $pending = DeductionRequest::query()
            ->where('status', 'pending')
            ->withSum('lines as cogs_total', 'line_total')
            ->with('show:id,title')
            ->latest()
            ->get();

        if ($pending->isEmpty()) {
            return ToolResult::ok('No deduction requests are pending approval.', ['count' => 0, 'total_cogs' => 0.0]);
        }

        $total = (float) $pending->sum('cogs_total');

        $lines = $pending->take(10)->map(fn (DeductionRequest $d) => sprintf(
            '%s: $%s',
            $d->show?->title ?? "request #{$d->id}",
            number_format((float) $d->cogs_total, 2),
        ))->implode('; ');

        return ToolResult::ok(
            $pending->count() . ' deduction request(s) pending approval, $' . number_format($total, 2) . ' in COGS. ' . $lines,
            ['count' => $pending->count(), 'total_cogs' => round($total, 2)],
        );
    }
}
