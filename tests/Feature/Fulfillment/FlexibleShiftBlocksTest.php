<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Services\FulfillmentShiftBlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlexibleShiftBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_unassigned_day_and_overnight_shifts(): void
    {
        Role::findOrCreate('fulfillment_admin', 'web');
        $manager = User::factory()->create();
        $manager->assignRole('fulfillment_admin');
        $service = app(FulfillmentShiftBlockService::class);

        $day = $service->create($manager, [
            'starts_at' => '2026-11-12 12:00:00', 'ends_at' => '2026-11-13 00:00:00',
            'kind' => 'day', 'label' => 'Day Team', 'required_staff' => 3,
        ]);
        $night = $service->create($manager, [
            'starts_at' => '2026-11-13 00:00:00', 'ends_at' => '2026-11-13 12:00:00',
            'kind' => 'night', 'label' => 'Night Team', 'required_staff' => 2,
        ]);

        $this->assertDatabaseHas('fulfillment_shift_blocks', ['id' => $day, 'required_staff' => 3]);
        $this->assertDatabaseHas('fulfillment_shift_blocks', ['id' => $night, 'kind' => 'night']);
        $this->assertSame(0, DB::table('fulfillment_shift_assignments')->count());
    }

    public function test_assignment_requires_fulfillment_role_and_rejects_overlaps(): void
    {
        foreach (['fulfillment_admin', 'fulfillment'] as $role) Role::findOrCreate($role, 'web');
        $manager = User::factory()->create();
        $manager->assignRole('fulfillment_admin');
        $worker = User::factory()->create();
        $worker->assignRole('fulfillment');
        $service = app(FulfillmentShiftBlockService::class);
        $first = $service->create($manager, ['starts_at' => '2026-11-12 12:00:00', 'ends_at' => '2026-11-13 00:00:00', 'required_staff' => 2]);
        $second = $service->create($manager, ['starts_at' => '2026-11-12 15:00:00', 'ends_at' => '2026-11-13 03:00:00', 'required_staff' => 2]);

        $service->assign($manager, $first, $worker->id);
        $this->assertDatabaseHas('fulfillment_shift_assignments', ['shift_block_id' => $first, 'user_id' => $worker->id]);
        $this->expectException(ValidationException::class);
        $service->assign($manager, $second, $worker->id);
    }
}
