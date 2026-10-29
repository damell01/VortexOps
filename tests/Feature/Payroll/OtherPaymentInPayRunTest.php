<?php
namespace Tests\Feature\Payroll;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Models\{Payout, Streamer, User, WeeklyPayoutBatch};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OtherPaymentInPayRunTest extends TestCase
{
    use RefreshDatabase;

    private WeeklyPayoutBatch $run;
    private Streamer $person;
    private Payout $payout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']));
        $this->person = Streamer::create(['name' => 'Other Pay fixture', 'status' => 'active', 'payout_type' => 'hourly', 'hourly_rate' => 50]);
        $this->run = WeeklyPayoutBatch::create(['week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft']);
        $this->payout = Payout::create(['streamer_id' => $this->person->id, 'weekly_payout_batch_id' => $this->run->id, 'payout_type' => 'hourly', 'hours_worked' => 2, 'calculated_payout' => 100, 'status' => 'draft']);
        $this->run->recalculateTotal();
    }

    public function test_other_payment_is_added_inside_the_existing_run_with_its_description(): void
    {
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $this->run->id])
            ->callAction('other_payment', ['streamer_id' => $this->person->id, 'assign_show' => false, 'amount' => 25.50, 'description' => 'Fixture bonus'])
            ->assertHasNoActionErrors();
        $this->assertEquals(125.50, (float) $this->payout->fresh()->calculated_payout);
        $this->assertEquals(125.50, (float) $this->run->fresh()->total_payout);
        $this->assertStringContainsString('Fixture bonus', $this->payout->fresh()->calculation_notes);
        $this->assertDatabaseCount('weekly_payout_batches', 1);
        $this->assertDatabaseCount('payouts', 1);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'pay_run', 'description' => 'Other payment added', 'subject_id' => $this->payout->id]);
    }

    public function test_invalid_amount_does_not_change_pay(): void
    {
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $this->run->id])
            ->callAction('other_payment', ['streamer_id' => $this->person->id, 'assign_show' => false, 'amount' => 0, 'description' => 'Invalid payment'])
            ->assertHasActionErrors(['amount']);
        $this->assertEquals(100, (float) $this->payout->fresh()->calculated_payout);
    }

    public function test_other_payment_is_unavailable_after_the_run_is_finalized(): void
    {
        $this->run->update(['status' => 'finalized']);
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $this->run->id])->assertActionHidden('other_payment');
        $this->assertEquals(100, (float) $this->payout->fresh()->calculated_payout);
    }
}
