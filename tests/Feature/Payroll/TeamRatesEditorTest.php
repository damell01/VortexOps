<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\PaymentStructures;
use App\Models\User;
use App\Support\PaymentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Team rates are edited in a pop-up: cancel throws the edit away, save keeps it. */
class TeamRatesEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        $this->actingAs(User::factory()->create(['email' => 'owner@test.com']));
        PaymentStructure::saveDefaults('streamer', ['payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 8, 'include_tips' => true]);
    }

    public function test_cancel_puts_the_saved_rates_back(): void
    {
        Livewire::test(PaymentStructures::class)
            ->assertSee('Edit team rates')
            ->call('openRatesEditor')->assertSet('editing_rates', true)
            ->set('streamer.payout_percentage', 25)
            ->call('cancelRatesEditor')
            ->assertSet('editing_rates', false)
            ->assertSet('streamer.payout_percentage', 8);

        $this->assertEquals(8, PaymentStructure::defaults('streamer')['payout_percentage']);
    }

    public function test_save_keeps_the_new_rates_and_closes(): void
    {
        Livewire::test(PaymentStructures::class)
            ->call('openRatesEditor')
            ->set('streamer.payout_percentage', 12)
            ->call('saveRates')
            ->assertHasNoErrors()
            ->assertSet('editing_rates', false)
            ->assertSee('12.00%');

        $this->assertEquals(12, PaymentStructure::defaults('streamer')['payout_percentage']);
    }
}
