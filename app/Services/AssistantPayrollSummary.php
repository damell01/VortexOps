<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class AssistantPayrollSummary
{
    public function hours(string $start, string $end): Collection
    {
        return User::role('streamer_assistant')->get()->map(function (User $user) use ($start, $end) {
            $entries = TimeEntry::query()->where('user_id', $user->id)
                ->whereDate('clocked_in_at', '>=', $start)
                ->whereDate('clocked_in_at', '<=', $end)
                ->whereNotNull('clocked_out_at')->get();

            $minutes = $entries->sum(function (TimeEntry $entry) {
                if ($entry->status === 'approved') {
                    return max(0, (int) ($entry->approved_minutes ?? $entry->duration_minutes));
                }
                return $entry->status === 'ok' ? max(0, (int) $entry->duration_minutes) : 0;
            });

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'hours' => round($minutes / 60, 2),
                'pending_review' => $entries->where('status', 'flagged')->count(),
            ];
        });
    }

    public function tipsByChannel(string $start, string $end): array
    {
        $totals = [];
        $unassigned = 0.0;
        $entries = LedgerEntry::query()->with('show:id,whatnot_channel_id')
            ->where('type', 'tips')
            ->whereBetween('transaction_date', [$start, $end])->get();

        foreach ($entries as $entry) {
            $channelId = $entry->show?->whatnot_channel_id;
            if ($channelId === null) {
                $unassigned += $entry->signedAmount();
            } else {
                $totals[$channelId] = ($totals[$channelId] ?? 0) + $entry->signedAmount();
            }
        }

        return [
            'channels' => collect($totals)->map(fn ($value) => round($value, 2))->all(),
            'unassigned' => round($unassigned, 2),
            'source' => 'ledger_entries',
        ];
    }
}
