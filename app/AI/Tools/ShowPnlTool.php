<?php

namespace App\AI\Tools;

use App\AI\Contracts\Tool;
use App\AI\DTOs\ToolResult;
use App\Models\Show;
use App\Models\User;

/**
 * "How did <show> do?" — returns the profit-and-loss summary for a show by
 * title, or the most recent show when no title is given.
 */
final class ShowPnlTool implements Tool
{
    public function name(): string
    {
        return 'show.pnl';
    }

    public function description(): string
    {
        return 'Get the profit-and-loss summary (gross, net, COGS, payouts, margin) for a show. Provide a title to find a specific show, or omit it for the most recent show.';
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'description' => 'Show title to search for. Omit for the most recent show.', 'required' => false],
        ];
    }

    public function authorize(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function run(array $arguments): ToolResult
    {
        $title = trim((string) ($arguments['title'] ?? ''));

        $query = Show::query()->with('latestDeductionRequest.lines');

        $show = $title !== ''
            ? $query->where('title', 'like', "%{$title}%")->latest('show_date')->first()
            : $query->latest('show_date')->first();

        if (! $show) {
            return ToolResult::error($title !== '' ? "No show matches \"{$title}\"." : 'There are no shows yet.');
        }

        $pnl = $show->profitAndLoss();

        $summary = sprintf(
            '%s (%s): gross $%s, net $%s, COGS $%s, payouts $%s → margin $%s (%s%%).',
            $show->title,
            optional($show->show_date)->toDateString() ?? 'no date',
            number_format($pnl['gross'], 2),
            number_format($pnl['net'], 2),
            number_format($pnl['cogs'], 2),
            number_format($pnl['payouts'], 2),
            number_format($pnl['margin'], 2),
            $pnl['margin_pct'],
        );

        return ToolResult::ok($summary, ['show' => $show->title, 'pnl' => $pnl]);
    }
}
