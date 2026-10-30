<?php
namespace Tests\Feature\Payroll;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Models\{Payout, Streamer, User, WeeklyPayoutBatch, Show, Business, TimeEntry};
use App\Services\{OtherPayService, PayRunAutomationService, PayoutService};
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
        $this->assertEquals(100, (float) $this->payout->fresh()->calculated_payout);
        $extra = $this->run->payouts()->where('payout_type', OtherPayService::TYPE)->sole();
        $this->assertEquals(25.50, (float) $extra->calculated_payout);
        $this->assertEquals(125.50, (float) $this->run->fresh()->total_payout);
        $this->assertStringContainsString('Fixture bonus', $extra->calculation_notes);
        $this->assertDatabaseCount('weekly_payout_batches', 1);
        $this->assertDatabaseCount('payouts', 2);
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
    public function test_general_extras_survive_repeated_clock_recalculation_and_removed_hours(): void
    {
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('streamer_assistant', 'web'));
        $this->person->update(['user_id' => $user->id, 'member_type' => 'assistant']);
        $this->payout->update(['payout_type' => 'clocked_time']);
        TimeEntry::create(['user_id' => $user->id, 'clocked_in_at' => '2026-10-20 09:00', 'clocked_out_at' => '2026-10-20 11:00', 'reason' => 'other', 'status' => 'ok']);
        Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $this->run->id])
            ->callAction('other_payment', ['streamer_id' => $this->person->id, 'assign_show' => false, 'amount' => 25.50, 'description' => 'Keep bonus'])
            ->assertHasNoActionErrors();
        $service = app(PayRunAutomationService::class);
        $service->syncWeek('2026-10-19', true);
        $service->syncWeek('2026-10-19', true);
        $this->assertEquals(125.50, (float) $this->run->fresh()->total_payout);
        TimeEntry::query()->delete();
        $service->syncWeek('2026-10-19', true);
        $this->assertEquals(25.50, (float) $this->run->fresh()->total_payout);
        $this->assertSame(1, $this->run->payouts()->count());
    }

    public function test_show_recalculation_does_not_reuse_the_manual_adjustment_as_wages(): void
    {
        $show = Show::create(['title' => 'Other pay show', 'show_date' => '2026-10-20', 'status' => 'draft']);
        $show->streamers()->attach($this->person->id, ['is_primary' => true]);
        $this->payout->update(['show_id' => $show->id]);
        $extra = app(OtherPayService::class)->add($this->payout, 19.99, 'Show bonus');
        $engine = app(PayoutService::class);
        $engine->calculateForShow($show);
        $engine->calculateForShow($show);
        $this->assertEquals(19.99, (float) $extra->fresh()->calculated_payout);
        $this->assertSame(OtherPayService::TYPE, $extra->fresh()->payout_type);
        $this->assertSame($this->run->id, $extra->fresh()->weekly_payout_batch_id);
        $this->assertSame(2, Payout::where('show_id', $show->id)->count());
        // A blocked show can leave the draft, but its deliberately added bonus stays.
        app(PayRunAutomationService::class)->syncWeek('2026-10-19', true);
        $this->assertSame($this->run->id, $extra->fresh()->weekly_payout_batch_id);
    }

    public function test_legacy_extras_are_separated_once_without_changing_the_total(): void
    {
        $this->payout->update(['calculated_payout' => 135.51, 'calculation_notes' => "Original wages\n[OTHER_PAYMENT] +$25.50: First bonus\n[OTHER_PAYMENT] +$10.01: Second bonus"]);
        $this->run->recalculateTotal();
        $service = app(OtherPayService::class);
        $service->preserveLegacy($this->payout);
        $service->preserveLegacy($this->payout);
        $this->run->recalculateTotal();
        $this->assertEquals(100, (float) $this->payout->fresh()->calculated_payout);
        $this->assertEquals(135.51, (float) $this->run->fresh()->total_payout);
        $this->assertSame(2, $this->run->payouts()->where('payout_type', OtherPayService::TYPE)->count());
        $this->assertStringNotContainsString('[OTHER_PAYMENT]', $this->payout->fresh()->calculation_notes);
    }

    public function test_finalized_legacy_pay_is_never_converted(): void
    {
        $this->payout->update(['calculation_notes' => '[OTHER_PAYMENT] +$25.50: Old bonus']);
        $this->run->update(['status' => 'finalized']);
        app(OtherPayService::class)->preserveLegacy($this->payout);
        $this->assertDatabaseCount('payouts', 1);
        $this->assertStringContainsString('[OTHER_PAYMENT]', $this->payout->fresh()->calculation_notes);
    }
}
