<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\Streamer;
use App\Models\User;

/**
 * "What do we owe <streamer>?" — reports a streamer's outstanding payout
 * balance, or the roster-wide total when no name is given.
 */
final class StreamerBalanceTool implements Tool
{
    public function name(): string
    {
        return 'streamer.balance';
    }

    public function description(): string
    {
        return 'Report how much is owed to a streamer (earnings due minus paid). Provide a name for one streamer, or omit it for the total owed across all active streamers.';
    }

    public function parameters(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Streamer name to look up. Omit for the roster-wide total.', 'required' => false],
        ];
    }

    public function authorize(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function run(array $arguments): ToolResult
    {
        $name = trim((string) ($arguments['name'] ?? ''));

        if ($name === '') {
            $streamers = Streamer::where('status', 'active')->get();
            $total = $streamers->sum(fn (Streamer $s) => $s->outstandingBalance());

            return ToolResult::ok(
                'Total owed across ' . $streamers->count() . ' active streamers: $' . number_format($total, 2) . '.',
                ['total_outstanding' => round($total, 2), 'active_streamers' => $streamers->count()],
            );
        }

        $streamer = Streamer::where('name', 'like', "%{$name}%")->first();

        if (! $streamer) {
            return ToolResult::error("No streamer matches \"{$name}\".");
        }

        $balance = $streamer->outstandingBalance();

        return ToolResult::ok(
            "{$streamer->name} is owed \$" . number_format($balance, 2)
            . ' (due $' . number_format((float) $streamer->total_earnings_due, 2)
            . ', paid $' . number_format((float) $streamer->total_earnings_paid, 2) . ').',
            [
                'streamer'    => $streamer->name,
                'outstanding' => round($balance, 2),
                'due'         => round((float) $streamer->total_earnings_due, 2),
                'paid'        => round((float) $streamer->total_earnings_paid, 2),
            ],
        );
    }
}
