<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentPayrollProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_fulfillment_role_creates_payroll_profile(): void
    {
        Role::findOrCreate('fulfillment', 'web');
        $user = User::factory()->create();
        $user->assignRole('fulfillment');

        $this->assertDatabaseHas('streamers', [
            'user_id' => $user->id,
            'member_type' => 'fulfillment',
        ]);
    }

    public function test_removing_fulfillment_role_keeps_profile_but_changes_category(): void
    {
        Role::findOrCreate('fulfillment', 'web');
        $user = User::factory()->create();
        $user->assignRole('fulfillment');
        $profileId = $user->streamer()->value('id');

        $user->removeRole('fulfillment');

        $this->assertDatabaseHas('streamers', [
            'id' => $profileId,
            'user_id' => $user->id,
            'member_type' => 'other',
        ]);
        $this->assertSame(1, $user->streamer()->count());
    }

    public function test_assigning_fulfillment_admin_also_creates_fulfillment_profile(): void
    {
        Role::findOrCreate('fulfillment_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('fulfillment_admin');

        $this->assertDatabaseHas('streamers', [
            'user_id' => $user->id,
            'member_type' => 'fulfillment',
        ]);
    }
}
