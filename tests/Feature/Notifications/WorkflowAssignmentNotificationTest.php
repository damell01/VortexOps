<?php

namespace Tests\Feature\Notifications;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Models\User;
use App\Notifications\VortexAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_fulfillment_assignment_notification_links_to_registered_queue(): void
    {
        Notification::fake();

        Role::findOrCreate('fulfillment', 'web');
        $worker = User::factory()->create();
        $worker->assignRole('fulfillment');
        $show = Show::create([
            'title' => 'Packing assignment test',
            'show_date' => now()->toDateString(),
            'status' => 'reconciled',
            'created_by' => $worker->id,
        ]);
        $show->fulfillmentUsers()->attach($worker->id);

        // Exercise the actual scheduled command, not just the route helper.
        // The legacy FulfillmentCenter page no longer owns the registered URL.
        $this->artisan('workflow:notify-state')->assertExitCode(0);

        $expectedUrl = FulfillmentResource::getUrl('index');
        $this->assertStringContainsString('/admin/fulfillment-center', $expectedUrl);

        Notification::assertSentTo($worker, VortexAlert::class, function (VortexAlert $alert) use ($expectedUrl): bool {
            return $alert->title === 'Fulfillment show assigned'
                && ($alert->links['Open Fulfillment Center'] ?? null) === $expectedUrl;
        });
    }
}
