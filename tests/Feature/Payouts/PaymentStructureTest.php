<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Streamer;
use App\Support\PaymentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_member_is_not_silently_changed_until_adopted(): void
    {
        $member = Streamer::create([
            'name' => 'Legacy Streamer',
            'member_type' => 'streamer',
            'status' => 'active',
            'payout_type' => 'profit_share',
            'payout_percentage' => 8,
            'include_tips' => true,
        ]);

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'profit_share',
            'payout_cadence' => 'weekly',
            'payout_percentage' => 9,
            'include_tips' => true,
        ]);

        $this->assertSame(8.0, (float) $member->fresh()->payout_percentage);
        $this->assertTrue($member->fresh()->effectiveCompensation()['legacy']);

        PaymentStructure::adoptDefaults($member->fresh());

        $member->refresh();
        $this->assertSame(9.0, (float) $member->payout_percentage);
        $this->assertSame([], $member->compensation_override_fields);
        $this->assertFalse($member->effectiveCompensation()['legacy']);
    }

    public function test_default_changes_flow_to_inheritors_but_not_overridden_fields(): void
    {
        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'profit_share',
            'payout_cadence' => 'weekly',
            'payout_percentage' => 8,
            'hourly_rate' => 15,
            'include_tips' => true,
        ]);

        $normal = Streamer::create([
            'name' => 'Normal', 'member_type' => 'streamer', 'status' => 'active',
            'payout_type' => 'profit_share', 'payout_percentage' => 1, 'include_tips' => true,
        ]);
        $custom = Streamer::create([
            'name' => 'Custom', 'member_type' => 'streamer', 'status' => 'active',
            'payout_type' => 'profit_share', 'payout_percentage' => 1, 'include_tips' => true,
        ]);

        PaymentStructure::adoptDefaults($normal);
        PaymentStructure::adoptDefaults($custom);
        PaymentStructure::saveOverrides($custom->fresh(), ['payout_percentage' => 10]);

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'profit_share',
            'payout_cadence' => 'weekly',
            'payout_percentage' => 9,
            'hourly_rate' => 17,
            'include_tips' => true,
        ]);

        $normal->refresh();
        $custom->refresh();

        $this->assertSame(9.0, (float) $normal->payout_percentage);
        $this->assertSame(17.0, (float) $normal->hourly_rate);
        $this->assertSame(10.0, (float) $custom->payout_percentage);
        $this->assertSame(17.0, (float) $custom->hourly_rate);
        $this->assertSame(['payout_percentage'], $custom->compensation_override_fields);
    }

    public function test_payout_snapshots_effective_compensation(): void
    {
        PaymentStructure::saveDefaults('fulfillment', [
            'payout_type' => 'pwe_labels',
            'payout_cadence' => 'weekly',
            'hourly_rate' => 15,
            'label_rate' => 0.25,
            'pwe_rate' => 0.10,
            'include_tips' => false,
        ]);

        $member = Streamer::create([
            'name' => 'Fulfillment One',
            'member_type' => 'fulfillment',
            'status' => 'active',
            'payout_type' => 'pwe_labels',
            'include_tips' => false,
        ]);
        PaymentStructure::adoptDefaults($member);
        PaymentStructure::saveOverrides($member->fresh(), ['hourly_rate' => 17]);

        $payout = Payout::create([
            'streamer_id' => $member->id,
            'payout_type' => 'pwe_labels',
            'calculated_payout' => 100,
            'gross_show_revenue' => 0,
            'owner_fee_deducted' => 0,
            'tips_included' => 0,
            'status' => 'draft',
        ]);

        $payout->refresh();
        $this->assertSame('role-defaults-v1', $payout->calculation_version);
        $this->assertSame('fulfillment', $payout->compensation_snapshot['member_type']);
        $this->assertSame(17.0, (float) $payout->compensation_snapshot['effective']['hourly_rate']);
        $this->assertSame(0.25, (float) $payout->compensation_snapshot['effective']['label_rate']);
    }
}
