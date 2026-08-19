<?php

namespace Tests\Feature\Ui;

use App\Support\GuidedTours;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guided tours point at real elements on real screens, and introduce
 * themselves once. Both are easy to get wrong in ways nobody notices until a
 * new person follows a tour that describes a button that is not there.
 */
class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $seen = []): User
    {
        return User::factory()->create(['completed_tours' => $seen]);
    }

    public function test_every_mapped_route_points_at_a_tour_that_exists(): void
    {
        foreach (GuidedTours::routeMap() as $route => $tourId) {
            $this->assertContains(
                $tourId,
                GuidedTours::ids(),
                "route {$route} is mapped to tour '{$tourId}', which is not defined",
            );
        }
    }

    public function test_every_mapped_route_is_a_route_the_app_actually_has(): void
    {
        // A renamed resource would otherwise leave a tour silently attached to
        // nothing, which looks exactly like the feature being off.
        foreach (array_keys(GuidedTours::routeMap()) as $route) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($route),
                "tour is mapped to route '{$route}', which does not exist",
            );
        }
    }

    public function test_every_tour_has_steps_with_something_to_say(): void
    {
        foreach (GuidedTours::definitions() as $id => $tour) {
            $this->assertNotEmpty($tour['steps'], "tour '{$id}' has no steps");

            foreach ($tour['steps'] as $i => $step) {
                $this->assertNotEmpty($step['title'] ?? '', "tour '{$id}' step {$i} has no title");
                $this->assertNotEmpty($step['body'] ?? '', "tour '{$id}' step {$i} has no body");
            }
        }
    }

    public function test_the_first_step_of_every_tour_needs_no_element(): void
    {
        // Steps whose selector matches nothing are dropped at runtime. If the
        // opening step were one of those, a tour could silently start midway
        // through — or not at all.
        foreach (GuidedTours::definitions() as $id => $tour) {
            $this->assertArrayNotHasKey(
                'el',
                $tour['steps'][0],
                "tour '{$id}' opens with a step tied to an element, so it can vanish",
            );
        }
    }

    public function test_a_tour_opens_itself_for_someone_who_has_not_seen_it(): void
    {
        $tour = GuidedTours::forRoute('filament.admin.resources.inventory-items.index', $this->user());

        $this->assertNotNull($tour);
        $this->assertTrue($tour['auto']);
    }

    public function test_a_dismissed_tour_stops_opening_itself(): void
    {
        $tour = GuidedTours::forRoute(
            'filament.admin.resources.inventory-items.index',
            $this->user(['inventory-list']),
        );

        // Still returned, because the launcher stays available to replay it.
        $this->assertNotNull($tour);
        $this->assertFalse($tour['auto']);
    }

    public function test_a_route_with_no_tour_gets_nothing(): void
    {
        $this->assertNull(GuidedTours::forRoute('filament.admin.pages.dashboard', $this->user()));
        $this->assertNull(GuidedTours::forRoute(null, $this->user()));
    }

    public function test_completing_a_tour_is_recorded_against_the_user(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson(route('tours.complete'), ['tour' => 'inventory-list'])
            ->assertOk();

        $this->assertSame(['inventory-list'], $user->fresh()->completed_tours);
    }

    public function test_completing_the_same_tour_twice_does_not_duplicate_it(): void
    {
        $user = $this->user(['inventory-list']);

        $this->actingAs($user)->postJson(route('tours.complete'), ['tour' => 'inventory-list'])->assertOk();

        $this->assertSame(['inventory-list'], $user->fresh()->completed_tours);
    }

    public function test_an_unknown_tour_id_is_rejected(): void
    {
        // The id comes from the browser, so it is not trusted.
        $this->actingAs($this->user())
            ->postJson(route('tours.complete'), ['tour' => 'made-up'])
            ->assertStatus(422);
    }

    public function test_completing_a_tour_requires_signing_in(): void
    {
        $this->postJson(route('tours.complete'), ['tour' => 'inventory-list'])
            ->assertStatus(401);
    }

    public function test_tours_can_all_be_shown_again(): void
    {
        // For someone new sitting at an account that has already dismissed
        // everything, or a screen that changed enough to be worth re-watching.
        $user = $this->user(['inventory-list', 'payouts']);

        $this->actingAs($user)->postJson(route('tours.reset'))->assertOk();

        $this->assertSame([], $user->fresh()->completed_tours);

        $tour = GuidedTours::forRoute('filament.admin.resources.inventory-items.index', $user->fresh());
        $this->assertTrue($tour['auto'], 'a reset tour did not start opening itself again');
    }

    public function test_resetting_tours_requires_signing_in(): void
    {
        $this->postJson(route('tours.reset'))->assertStatus(401);
    }
}
