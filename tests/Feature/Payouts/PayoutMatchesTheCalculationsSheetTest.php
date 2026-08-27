<?php

namespace Tests\Feature\Payouts;

use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the pay run pays, against what the streamer's own sheet says.
 *
 * The payout engine worked a profit share out as whatnot_net × percentage —
 * Whatnot's own net, with no product cost and no burden — while every streamer
 * filled in a Calculations tab that subtracts both. On the show the formula was
 * lifted from the two disagreed by about 15%, and payroll fixed it by hand
 * every week.
 */
class PayoutMatchesTheCalculationsSheetTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->channel = WhatnotChannel::create(['name' => 'Sheet', 'status' => 'active']);

        // The rates the business actually runs on.
        Setting::set('payroll_burden_per_shipment', '2.10');
        Setting::set('payroll_burden_per_hour', '80.00');
    }

    /** The signed show from 8/13/26. */
    private function signedShow(): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Free Storm Emeralda',
            'show_date'          => today()->toDateString(),
            'gross_revenue'      => 7371.10,
            'whatnot_net'        => 6500.00,   // deliberately not the basis any more
            'tips'               => 0,
            'show_duration'      => 267,
            'status'             => 'reconciled',
        ]);
    }

    private function streamer(array $attributes = []): Streamer
    {
        return Streamer::create(array_merge([
            'name'              => 'Caylen',
            'status'            => 'active',
            'payout_type'       => 'profit_share',
            'payout_percentage' => 8,
            'include_tips'      => false,
        ], $attributes));
    }

    private function report(Show $show, Streamer $streamer, array $attributes = []): StreamerLogEntry
    {
        return StreamerLogEntry::create(array_merge([
            'show_id'             => $show->id,
            'streamer_id'         => $streamer->id,
            'status'              => 'admin_approved',
            'product_cost'        => 3392.00,
            'hours_streamed'      => 4.45,
            'number_of_shipments' => 80,
        ], $attributes));
    }

    private function payoutFor(Show $show): \App\Models\Payout
    {
        return app(PayoutService::class)->calculateForShow($show->fresh())[0];
    }

    public function test_the_payout_is_the_number_on_the_paperwork(): void
    {
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        // 80 × 2.10 + 4.45 × 80 = 524.00
        // 7371.10 − 3392.00 − 524.00 = 3455.10
        // 3455.10 × 8% = 276.41
        $this->assertSame(276.41, (float) $this->payoutFor($show)->calculated_payout);
    }

    public function test_it_is_no_longer_a_share_of_whatnot_net(): void
    {
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        // The old answer, from a figure the formula does not read any more.
        $this->assertNotSame(520.00, (float) $this->payoutFor($show)->calculated_payout);
    }

    public function test_the_payout_carries_the_numbers_it_was_worked_out_from(): void
    {
        // So a payout can be checked without reopening the report and redoing
        // the arithmetic by hand.
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        $payout = $this->payoutFor($show);

        $this->assertSame('3392.00', (string) $payout->product_cost);
        $this->assertSame('4.45', (string) $payout->hours_worked);
        $this->assertSame(80, $payout->shipments_count);
        $this->assertSame('524.00', (string) $payout->burden_amount);
        $this->assertSame('3455.10', (string) $payout->net_revenue_basis);
    }

    public function test_the_notes_show_the_working(): void
    {
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        $notes = $this->payoutFor($show)->calculation_notes;

        $this->assertStringContainsString('80 shipments × $2.10', $notes);
        $this->assertStringContainsString('$524.00', $notes);
        $this->assertStringContainsString('8% = $276.41', $notes);
    }

    public function test_with_no_burden_configured_it_is_gross_minus_product_cost(): void
    {
        Setting::set('payroll_burden_per_shipment', '');
        Setting::set('payroll_burden_per_hour', '');

        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        // (7371.10 − 3392.00) × 8% = 318.33
        $payout = $this->payoutFor($show);

        $this->assertSame(318.33, (float) $payout->calculated_payout);
        $this->assertSame('0.00', (string) $payout->burden_amount);
        $this->assertStringContainsString('No burden configured', $payout->calculation_notes);
    }

    public function test_the_report_beats_the_shows_recorded_length(): void
    {
        // The person who ran the show is the authority on how long it ran.
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer, ['hours_streamed' => 6.00]);

        // Burden 80 × 2.10 + 6 × 80 = 648.00 → net 3331.10 → 8% = 266.49
        $this->assertSame(266.49, (float) $this->payoutFor($show)->calculated_payout);
    }

    public function test_a_show_with_no_report_uses_the_same_formula(): void
    {
        // A blank cell on the sheet is a zero, so a missing product cost is a
        // zero here. One formula for every show in the run — the alternative
        // was two shows worked out different ways with nothing on the screen
        // to say which was which. The sign-off gate names this show before the
        // run can be finalised.
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $payout = $this->payoutFor($show);

        // Burden falls to hours only (4.45 × $80 = $356.00 — no shipments on
        // this show), net 7371.10 − 0 − 356.00 = 7015.10, × 8% = 561.21.
        $this->assertSame(561.21, (float) $payout->calculated_payout);
        $this->assertSame('0.00', (string) $payout->product_cost);
        $this->assertStringContainsString('No show report filed', $payout->calculation_notes);
    }

    public function test_a_non_primary_on_a_collab_still_gets_no_revenue_share(): void
    {
        $show    = $this->signedShow();
        $primary = $this->streamer(['name' => 'Primary']);
        $second  = $this->streamer(['name' => 'Second']);

        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $show->streamers()->attach($second->id, ['is_primary' => false]);
        $this->report($show, $primary);

        $payouts = app(PayoutService::class)->calculateForShow($show->fresh());
        $byName  = collect($payouts)->keyBy(fn ($p) => $p->streamer->name);

        $this->assertSame(276.41, (float) $byName['Primary']->calculated_payout);
        $this->assertSame(0.0, (float) $byName['Second']->calculated_payout);
    }

    public function test_a_collab_reads_the_shows_single_report(): void
    {
        // streamer_log_entries.show_id is unique, so a show has one report
        // between everybody on it — both payouts are costed from the same one.
        $show    = $this->signedShow();
        $primary = $this->streamer(['name' => 'Primary']);
        $second  = $this->streamer(['name' => 'Second']);

        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $show->streamers()->attach($second->id, ['is_primary' => false]);

        $this->report($show, $primary, ['product_cost' => 3392.00]);

        $byName = collect(app(PayoutService::class)->calculateForShow($show->fresh()))
            ->keyBy(fn ($p) => $p->streamer->name);

        $this->assertSame('3392.00', (string) $byName['Primary']->product_cost);
        // The non-primary gets no revenue share, so no working is stored.
        $this->assertNull($byName['Second']->product_cost);
    }

    public function test_a_hybrid_profit_component_uses_the_same_basis(): void
    {
        // Otherwise the same words on two people's records pay two formulas.
        $show     = $this->signedShow();
        $streamer = $this->streamer([
            'payout_type'       => 'hybrid',
            'payout_percentage' => 8,
            'hourly_rate'       => 20,
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        // Hourly 4.45 × $20 = $89.00, plus the same $276.41 profit component.
        $this->assertSame(365.41, (float) $this->payoutFor($show)->calculated_payout);
    }

    public function test_the_working_is_on_the_payout_screen(): void
    {
        // The point of storing the inputs is that somebody can check the
        // amount against the sheet without opening the report.
        $show     = $this->signedShow();
        $streamer = $this->streamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        $payout = $this->payoutFor($show);

        \Livewire\Livewire::actingAs(
            \App\Models\User::firstWhere('email', config('app.owner_email'))
                ?? \App\Models\User::factory()->create(['email' => config('app.owner_email')]),
        )
            ->test(\App\Filament\Resources\PayoutResource\Pages\ViewPayout::class, ['record' => $payout->getKey()])
            ->assertSee('Profit Share Working')
            ->assertSee('3,392.00')
            ->assertSee('524.00')
            ->assertSee('3,455.10');
    }

    public function test_a_package_payout_carries_no_profit_share_working(): void
    {
        // A package rate has no net revenue behind it, and storing zeros would
        // read as "we worked it out and got nothing".
        $show     = $this->signedShow();
        $streamer = $this->streamer(['payout_type' => 'package', 'package_rate' => 150]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->report($show, $streamer);

        $payout = $this->payoutFor($show);

        $this->assertSame(150.0, (float) $payout->calculated_payout);
        $this->assertNull($payout->product_cost);
        $this->assertNull($payout->net_revenue_basis);
    }
}
