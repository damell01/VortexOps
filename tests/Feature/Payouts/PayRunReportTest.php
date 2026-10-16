<?php

namespace Tests\Feature\Payouts;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\BusinessStatement;
use App\Services\PayoutService;
use App\Services\PayRunReport;
use App\Support\Season;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The pay run report and the monthly business statement, both as PDFs. */
class PayRunReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Business $estill;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();
        $this->channel = WhatnotChannel::create(['name' => 'Estill Cards', 'status' => 'active']);
    }

    /** A show with pay lines on a run: $1,000 net, $300 product cost. */
    private function runWithPay(): array
    {
        $jay = Streamer::create(['name' => 'Jay', 'member_type' => 'streamer', 'status' => 'active', 'business_id' => $this->estill->id]);
        $show = Show::create(['whatnot_channel_id' => $this->channel->id, 'title' => 'Tuesday Breaks', 'show_date' => '2026-10-06',
            'gross_revenue' => 1200, 'whatnot_net' => 1000, 'tips' => 0, 'status' => 'reconciled', 'created_by' => $this->owner->id]);
        $show->streamers()->attach($jay->id, ['is_primary' => true]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $jay->id, 'status' => 'admin_approved', 'product_cost' => 300, 'hours_streamed' => 4]);
        $run = WeeklyPayoutBatch::create(['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'status' => 'draft', 'business_id' => $this->estill->id, 'pay_run_type_name' => 'Estill Weekly']);
        foreach ([['shift_share', 55], ['show_hours', 100], ['show_tips', 12]] as [$type, $amount]) {
            Payout::create(['show_id' => $show->id, 'streamer_id' => $jay->id, 'weekly_payout_batch_id' => $run->id, 'payout_type' => $type,
                'calculated_payout' => $amount, 'hours_worked' => $type === 'show_hours' ? 4 : 0, 'pwe_count' => $type === 'show_hours' ? 10 : 0, 'status' => 'draft']);
        }

        return [$run, $show, $jay];
    }

    public function test_the_report_lists_each_person_their_lines_and_the_transfer(): void
    {
        [$run] = $this->runWithPay();
        $data = app(PayRunReport::class)->build($run);

        $jay = $data['people']->first();
        $this->assertSame(['Jay', 1, 4.0, 10], [$jay['name'], $jay['shows'], $jay['hours'], $jay['envelopes']]);
        $this->assertSame([55.0, 100.0, 12.0, 167.0], [$jay['main'], $jay['wages'], $jay['tips'], $jay['total']]);
        $this->assertSame('Tuesday Breaks', $jay['show_lines'][0]['title']);
        $this->assertSame(167.0, $data['totals']['total']);
        $this->assertSame('Estill Weekly', $data['name']);
        // $300 product cost + ($1,000 net − $155 pay, tips aside) = $1,145.
        $this->assertSame(1145.0, $data['transfer']['transfer']);
        $this->assertStringContainsString('Jay', view('pdf.pay-run', $data)->render());
    }

    public function test_finalizing_and_paying_keep_a_copy_and_the_button_serves_them(): void
    {
        [$run] = $this->runWithPay();
        $report = app(PayRunReport::class);

        app(PayoutService::class)->finalizeBatch($run, force: true);
        Storage::disk('local')->assertExists($report->path($run, 'finalized'));
        $this->assertArrayHasKey('finalized', $report->kept($run));
        $this->assertArrayNotHasKey('paid', $report->kept($run));

        app(PayoutService::class)->markBatchPaid($run->fresh());
        Storage::disk('local')->assertExists($report->path($run, 'paid'));

        $this->get(route('export.pay-run-pdf', ['batch' => $run->id, 'stage' => 'paid']))->assertOk()->assertDownload($report->filename($run, 'paid'));
        $this->get(route('export.pay-run-pdf', ['batch' => $run->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_only_admins_can_download_it(): void
    {
        [$run] = $this->runWithPay();
        $this->actingAs(User::factory()->create());
        $this->get(route('export.pay-run-pdf', ['batch' => $run->id]))->assertForbidden();
        $this->get(route('export.business-statement', ['business' => $this->estill->id]))->assertForbidden();
    }

    public function test_the_monthly_statement_totals_the_businesss_shows(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00', 'America/Chicago'));
        config(['app.display_timezone' => 'America/Chicago']);
        $this->runWithPay();

        $data = app(BusinessStatement::class)->build($this->estill, Carbon::parse('2026-10-01'));
        $this->assertSame('October 2026', $data['month']);
        $this->assertSame(1, $data['totals']['shows']);
        $this->assertSame(1000.0, $data['totals']['net']);
        $this->assertSame(1, $data['weeks']->count());
        $this->assertSame('Estill Weekly', $data['runs']->first()->pay_run_type_name);
        $this->assertStringContainsString('Tuesday Breaks', view('pdf.business-statement', $data)->render());

        $this->get(route('export.business-statement', ['business' => $this->estill->id, 'month' => '2026-10']))
            ->assertOk()->assertDownload(app(BusinessStatement::class)->filename($this->estill, Carbon::parse('2026-10-01')));
    }

    public function test_your_buddy_takes_every_other_turn_as_the_visitor(): void
    {
        $cast = Season::visitorsFor('eevee', Carbon::parse('2026-10-20'));
        $this->assertSame(['eevee', 'gengar', 'eevee', 'haunter'], array_slice($cast['top'], 0, 4));
        $this->assertSame(Season::visitors(Carbon::parse('2026-10-20'))['bottom'], $cast['bottom']);
        $this->assertSame(Season::visitors(Carbon::parse('2026-10-20')), Season::visitorsFor(null, Carbon::parse('2026-10-20')));
        $this->assertSame(Season::visitors(Carbon::parse('2026-10-20')), Season::visitorsFor('../etc', Carbon::parse('2026-10-20')), 'only a real sprite');
        foreach (Season::RARE_VISITORS as $mon) {
            $this->assertFileExists(public_path("img/pokemon/shiny/{$mon}.gif"));
        }
    }
}
