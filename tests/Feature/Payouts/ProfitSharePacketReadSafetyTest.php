<?php

namespace Tests\Feature\Payouts;

use App\Livewire\StreamerProfitShareDashboard;
use App\Models\{ProfitSharePacket, Streamer, User};
use App\Services\ProfitShareCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitSharePacketReadSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_an_approved_packet_preserves_its_financials(): void
    {
        $streamer = Streamer::create(['name' => 'Host', 'status' => 'active']);
        $packet = ProfitSharePacket::create(['streamer_id' => $streamer->id, 'year' => now()->year, 'month' => now()->month, 'status' => 'approved', 'profit_share_amount' => 276.41, 'gross_revenue' => 7371.10]);
        $packet->refresh();
        $before = $packet->getRawOriginal();
        app(ProfitShareCalculationService::class)->updatePacketWithCalculations($packet);
        app(ProfitShareCalculationService::class)->finalizePacket($packet);
        $this->assertSame($before, $packet->fresh()->getRawOriginal());
    }

    public function test_the_dashboard_service_is_available_after_livewire_hydration(): void
    {
        $user = User::factory()->create();
        $streamer = Streamer::create(['name' => 'Host', 'status' => 'active', 'user_id' => $user->id]);
        $packet = ProfitSharePacket::create(['streamer_id' => $streamer->id, 'year' => now()->year, 'month' => now()->month, 'status' => 'approved', 'profit_share_amount' => 276.41]);
        Livewire::actingAs($user)->test(StreamerProfitShareDashboard::class, ['streamer' => $streamer])
            ->assertOk()->call('selectPacket', $packet->id)->assertOk();
        $this->assertEquals(276.41, $packet->fresh()->profit_share_amount);
    }
}
