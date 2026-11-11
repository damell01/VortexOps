<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\YearEndPay;
use App\Models\Payout;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Services\YearEndPaySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Year-end totals per person: runs marked paid in the year, by the date they were paid. */
class YearEndPayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'reporting']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
    }

    private function payRun(string $start, string $status, ?string $paidAt = null): WeeklyPayoutBatch
    {
        $run = WeeklyPayoutBatch::create(['week_start' => $start, 'week_end' => \Illuminate\Support\Carbon::parse($start)->addDays(6)->toDateString(), 'status' => $status]);
        if ($paidAt) DB::table('pay_run_report_snapshots')->insert(['weekly_payout_batch_id' => $run->id, 'stage' => 'paid', 'payload' => '{}', 'created_at' => $paidAt, 'updated_at' => $paidAt]);

        return $run;
    }

    private function line(WeeklyPayoutBatch $run, Streamer $who, float $amount, string $type = 'profit_share', float $tips = 0): void
    {
        Payout::create(['weekly_payout_batch_id' => $run->id, 'streamer_id' => $who->id, 'payout_type' => $type, 'calculated_payout' => $amount,
            'gross_show_revenue' => 0, 'owner_fee_deducted' => 0, 'tips_included' => $tips, 'status' => $run->status === 'paid' ? 'paid' : 'draft']);
    }

    public function test_totals_follow_the_date_a_run_was_paid(): void
    {
        $ana = Streamer::create(['name' => 'Ana', 'legal_name' => 'Ana Lopez', 'status' => 'active']);
        $ben = Streamer::create(['name' => 'Ben', 'status' => 'active']);

        // December work paid in January counts in January's year.
        $dec = $this->payRun('2025-12-22', 'paid', '2026-01-03 10:00:00');
        $this->line($dec, $ana, 400, 'profit_share', 20);
        $mar = $this->payRun('2026-03-02', 'paid', '2026-03-10 10:00:00');
        $this->line($mar, $ana, 600);
        $this->line($mar, $ana, -50, \App\Services\OtherPayService::TYPE);
        $this->line($mar, $ben, 300);
        $draft = $this->payRun('2026-11-02', 'draft');
        $this->line($draft, $ana, 999);                       // not paid yet: not counted

        $rows = app(YearEndPaySummary::class)->for(2026)->keyBy('name');
        $this->assertSame(950.0, $rows['Ana']['total']);
        $this->assertSame(20.0, $rows['Ana']['tips']);
        $this->assertSame(-50.0, $rows['Ana']['deductions']);
        $this->assertSame(2, $rows['Ana']['runs']);
        $this->assertSame('2026-01-03', $rows['Ana']['first_paid']);
        $this->assertNull($rows['Ben']['legal_name']);
        $this->assertCount(0, app(YearEndPaySummary::class)->for(2025));
        $this->assertSame(['Ana'], app(YearEndPaySummary::class)->for(2026, 500)->pluck('name')->all());

        Livewire::withQueryParams(['year' => 2026])->test(YearEndPay::class)
            ->assertSee('Ana Lopez')->assertSee('$950.00')->assertSee('No legal name on file')
            ->call('export')->assertFileDownloaded('year-end-pay-2026.csv');
    }
}
