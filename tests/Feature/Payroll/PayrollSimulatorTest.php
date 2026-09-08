<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\PayrollSimulator;
use App\Models\InventoryMovement;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollSimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    private function seedSandboxInputs(): void
    {
        Product::create([
            'name' => 'Real Catalog Box',
            'sku' => 'SIM-BOX',
            'is_active' => true,
            'unit_cost' => 25,
            'average_cost' => 30,
        ]);

        Product::create([
            'name' => 'Real Catalog Pack',
            'sku' => 'SIM-PACK',
            'is_active' => true,
            'unit_cost' => 8,
            'average_cost' => 10,
        ]);

        Streamer::create([
            'name' => 'Profit Share Tester',
            'status' => 'active',
            'member_type' => 'streamer',
            'payout_type' => 'profit_share',
            'payout_percentage' => 8,
            'include_tips' => true,
        ]);

        Streamer::create([
            'name' => 'Hourly Tester',
            'status' => 'active',
            'member_type' => 'streamer',
            'payout_type' => 'hourly',
            'hourly_rate' => 22,
        ]);
    }

    public function test_month_preset_builds_mock_weekly_runs_without_writing_operational_data(): void
    {
        $this->seedSandboxInputs();
        $admin = $this->admin();

        $before = [
            'shows' => Show::count(),
            'payouts' => Payout::count(),
            'runs' => WeeklyPayoutBatch::count(),
            'movements' => InventoryMovement::count(),
        ];

        Livewire::actingAs($admin);
        $component = Livewire::test(PayrollSimulator::class)
            ->assertOk()
            ->assertSet('mode', 'month')
            ->assertCount('shows', 12)
            ->call('loadPreset', 'single')
            ->assertSet('mode', 'single')
            ->assertCount('shows', 1)
            ->call('loadPreset', 'week')
            ->assertSet('mode', 'week')
            ->assertCount('shows', 4)
            ->call('loadPreset', 'month')
            ->assertCount('shows', 12);

        $sim = $component->instance()->simulation();
        $this->assertCount(4, $sim['weeks']);
        $this->assertGreaterThan(0, $sim['gross']);
        $this->assertGreaterThan(0, $sim['cogs']);
        $this->assertGreaterThanOrEqual(0, $sim['payroll']);

        $this->assertSame($before['shows'], Show::count());
        $this->assertSame($before['payouts'], Payout::count());
        $this->assertSame($before['runs'], WeeklyPayoutBatch::count());
        $this->assertSame($before['movements'], InventoryMovement::count());
    }

    public function test_non_admin_cannot_access_payroll_simulator(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertFalse(PayrollSimulator::canAccess());
    }
}
