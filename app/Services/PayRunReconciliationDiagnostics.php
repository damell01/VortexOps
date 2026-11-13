<?php

namespace App\Services;

use App\Models\WeeklyPayoutBatch;

/**
 * Informational checks only. Never calls payout calculators, writes models,
 * or participates in readiness/finalization decisions.
 */
final class PayRunReconciliationDiagnostics
{
    /** @return array<int, array{show_id:int, title:string, label:string, detail:string}> */
    public function for(WeeklyPayoutBatch $batch): array
    {
        $batch->loadMissing('payouts.show');
        $flags = [];

        foreach ($batch->payouts->pluck('show')->filter()->unique('id') as $show) {
            $label = (string) ($show->title ?: 'Show #'.$show->id);
            $add = static function (string $title, string $detail) use (&$flags, $show, $label): void {
                $flags[] = ['show_id' => (int) $show->id, 'title' => $title, 'label' => $label, 'detail' => $detail];
            };

            if (in_array((string) $show->analytics_sync_status, ['pending', 'failed', 'unavailable'], true)) {
                $add('Analytics not finalized', 'Whatnot API status: '.(string) $show->analytics_sync_status.'. Figures may be provisional.');
            }
            if ((bool) $show->financials_revised_after_lock) {
                $add('Financial revision after approval', 'The show was flagged for revised financial data after it was locked. Compare with saved pay lines.');
            }
            if ((bool) $show->channel_attribution_suspect) {
                $add('Channel attribution needs review', 'Confirm this show belongs to the correct Whatnot channel.');
            }
        }

        return $flags;
    }
}
