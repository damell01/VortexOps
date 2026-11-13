<?php

namespace Tests\Unit;

use App\Models\Payout;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunReconciliationDiagnostics;
use PHPUnit\Framework\TestCase;

class PayRunReconciliationDiagnosticsTest extends TestCase
{
    public function test_flags_are_informational_and_do_not_modify_saved_payouts(): void
    {
        $show = new Show([
            'title' => 'Test stream',
            'analytics_sync_status' => 'pending',
            'financials_revised_after_lock' => true,
            'channel_attribution_suspect' => true,
        ]);
        $show->id = 42;

        $payout = new Payout(['calculated_payout' => 125.50, 'status' => 'paid']);
        $payout->setRelation('show', $show);

        $batch = new WeeklyPayoutBatch(['status' => 'paid', 'total_payout' => 125.50]);
        $batch->setRelation('payouts', collect([$payout]));

        $flags = (new PayRunReconciliationDiagnostics())->for($batch);

        $this->assertCount(3, $flags);
        $this->assertSame(42, $flags[0]['show_id']);
        $this->assertSame('paid', $payout->status);
        $this->assertEquals(125.50, (float) $payout->calculated_payout);
        $this->assertEquals(125.50, (float) $batch->total_payout);
        $this->assertFalse($payout->isDirty());
    }
}
