<?php

namespace Tests\Feature\Reports;

use App\Models\Business;
use App\Models\Payout;
use App\Models\PayRunReportSnapshot;
use App\Models\Setting;
use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Support\Carbon;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunReport;
use App\Services\PayoutService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayRunReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 12:00:00');
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeRun(): WeeklyPayoutBatch
    {
        return WeeklyPayoutBatch::create(['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'status' => 'draft', 'total_payout' => 0]);
    }

    public function test_report_is_available_in_browser_and_as_inline_and_download_pdf(): void
    {
        $run = $this->makeRun();
        $url = route('financial-reports.pay-run', ['batch' => $run]);
        $this->get($url)->assertOk()->assertSee('Pay Run #'.$run->id)->assertSee('Draft preview');
        $this->get($url.'?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $response = $this->get($url.'?format=pdf&download=1')->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_streamers_cannot_read_payroll_or_business_statements(): void
    {
        $run = $this->makeRun();
        $this->actingAs(User::factory()->create());
        $this->get(route('financial-reports.pay-run', ['batch' => $run]))->assertForbidden();
        $this->get(route('financial-reports.statement'))->assertForbidden();
    }

    public function test_finalization_and_payment_save_distinct_immutable_copies(): void
    {
        $run = $this->makeRun();
        app(PayoutService::class)->finalizeBatch($run);
        $final = PayRunReportSnapshot::where('stage', 'finalized')->firstOrFail();
        $this->assertSame('Finalized', $final->payload['status']);
        $run->update(['notes' => 'Changed after finalization']);
        app(PayRunReport::class)->snapshot($run, 'finalized');
        $this->assertNull($final->fresh()->payload['notes']);
        app(PayoutService::class)->markBatchPaid($run);
        $this->assertSame(2, PayRunReportSnapshot::count());
        $this->assertSame('Paid', PayRunReportSnapshot::where('stage', 'paid')->firstOrFail()->payload['status']);
        $this->get(route('financial-reports.pay-run', ['batch' => $run, 'stage' => 'finalized']))->assertOk()->assertSee('Saved snapshot');
    }

    public function test_people_with_the_same_name_are_separate_and_pay_is_not_recalculated(): void
    {
        $run = $this->makeRun();
        foreach ([100, 200] as $amount) {
            $person = Streamer::create(['name' => 'Same Name', 'status' => 'active']);
            Payout::withoutEvents(fn () => Payout::create(['weekly_payout_batch_id' => $run->id, 'streamer_id' => $person->id, 'payout_type' => 'hourly', 'calculated_payout' => $amount, 'status' => 'draft']));
        }
        $data = app(PayRunReport::class)->run($run);
        $this->assertCount(2, $data['people']);
        $this->assertEquals(300, $data['total']);
        $this->assertEquals(300, $run->payouts()->sum('calculated_payout'));
    }

    public function test_statement_filters_business_and_month_and_validates_input(): void
    {
        $business = Business::create(['name' => 'Estill Distribution']);
        $this->get(route('financial-reports.statement', ['business' => $business->id, 'month' => '2026-09']))->assertOk()->assertSee('Estill Distribution')->assertSee('September 2026')->assertSee('No operational shows');
        $this->getJson(route('financial-reports.statement', ['business' => $business->id, 'month' => 'oops']))->assertUnprocessable();
        $this->get(route('financial-reports.pay-run', ['batch' => $this->makeRun(), 'stage' => 'paid']))->assertNotFound();
    }
    public function test_monthly_statement_does_not_include_other_businesses_or_months(): void
    {
        $estill = Business::create(['name' => 'Estill Distribution']);
        $other = Business::create(['name' => 'Other Business']);
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        foreach ([[$estill, '2026-09-15', 125], [$other, '2026-09-15', 900], [$estill, '2026-08-15', 700]] as [$business, $date, $net]) {
            $person = Streamer::create(['name' => $business->name, 'business_id' => $business->id, 'status' => 'active']);
            $show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Show '.$net, 'show_date' => $date, 'whatnot_net' => $net, 'gross_revenue' => $net, 'is_operational' => true, 'status' => 'reconciled']);
            $show->streamers()->attach($person->id, ['is_primary' => true]);
        }
        $report = app(PayRunReport::class)->month($estill, Carbon::parse('2026-09-01'));
        $this->assertCount(1, $report['lines']);
        $this->assertEquals(125, $report['transfer']['net']);
        $this->assertSame('Provisional', $report['status']);
    }

}
