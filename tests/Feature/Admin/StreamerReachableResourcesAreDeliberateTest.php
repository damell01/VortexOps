<?php

namespace Tests\Feature\Admin;

use App\Models\Streamer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Which resources a streamer can open is a decision, not a default.
 *
 * HasModuleAccess::passesModuleAccessCheck() returns true for anyone signed
 * in, so a new resource is reachable by every role the moment it is
 * registered — its author has to notice and either restrict access or scope
 * the rows. Four resources did neither and shipped that way, and nothing
 * failed, because nothing was watching.
 *
 * This is what watches. Add a resource and this test fails until you put it
 * on one side of the line:
 *
 *   - streamers should see it → scope getEloquentQuery() to the signed-in
 *     streamer (ShowResource is the pattern) and add it below;
 *   - they should not → override passesModuleAccessCheck() to require
 *     isAdmin().
 *
 * A list page never loads a record, so a record policy will not save you: the
 * loans list showed every balance while StreamerLoanPolicy correctly guarded
 * the pages behind it.
 */
class StreamerReachableResourcesAreDeliberateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resources a streamer is meant to reach. Each one scopes its own rows.
     *
     * @var array<int, class-string>
     */
    private const ALLOWED = [
        \App\Filament\Resources\DeductionRequestResource::class,
        \App\Filament\Resources\FeedbackTicketResource::class,
        \App\Filament\Resources\InventoryItemResource::class,
        \App\Filament\Resources\InventoryLocationResource::class,
        \App\Filament\Resources\PayoutResource::class,
        \App\Filament\Resources\ShipmentResource::class,
        \App\Filament\Resources\ShowResource::class,
        \App\Filament\Resources\StreamerLogResource::class,
        \App\Filament\Resources\StreamerResource::class,
    ];

    public function test_no_resource_becomes_streamer_reachable_by_accident(): void
    {
        Role::findOrCreate('streamer', 'web');

        $user = User::factory()->create(['email' => 'streamer@example.com']);
        $user->assignRole('streamer');

        Streamer::create([
            'name' => 'Streamer', 'email' => 'streamer@example.com',
            'user_id' => $user->id, 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $this->actingAs($user->fresh());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $reachable = [];

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (rescue(fn () => $resource::canAccess(), false, false)) {
                $reachable[] = $resource;
            }
        }

        sort($reachable);
        $expected = self::ALLOWED;
        sort($expected);

        $this->assertSame(
            array_map('class_basename', $expected),
            array_map('class_basename', $reachable),
            "A resource changed sides. Either scope its rows to the signed-in streamer and add it to ALLOWED, "
            . "or override passesModuleAccessCheck() to require isAdmin()."
        );
    }
}
