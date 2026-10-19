<?php

namespace Tests\Feature\Admin;

use App\Models\{InventoryMovement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataCreatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_uses_the_real_seeded_admin_even_when_id_one_is_unavailable(): void
    {
        $previous = User::factory()->create();
        $previous->delete();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        $admin = User::where('email', 'admin@vortexbreaks.com')->firstOrFail();
        $this->assertNotSame($previous->id, $admin->id);
        $this->assertGreaterThan(0, InventoryMovement::count());
        $this->assertSame([$admin->id], InventoryMovement::whereNotNull('created_by')->distinct()->pluck('created_by')->all());
    }
}
