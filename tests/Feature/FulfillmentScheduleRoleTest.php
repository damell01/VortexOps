<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FulfillmentScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentScheduleRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_fulfillment_employee_cannot_be_scheduled(): void
    {
        Role::findOrCreate('fulfillment_admin', 'web');
        Role::findOrCreate('fulfillment', 'web');

        $manager = User::factory()->create();
        $manager->assignRole('fulfillment_admin');
        $employee = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(FulfillmentScheduleService::class)->create(
            $manager,
            $employee->id,
            '2026-11-09 09:00:00',
            '2026-11-09 17:00:00'
        );
    }
}
