<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\ErrorAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** A crash in the app tells the owner, once an hour per error; everyday 4xx outcomes never do. */
class ErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.error_alerts' => true, 'app.debug' => false]);
        Route::middleware('web')->get('/_test/crash', fn () => throw new \RuntimeException('Undefined variable $shipment'));
        Route::middleware('web')->get('/_test/missing', fn () => abort(404));
    }

    public function test_a_crash_alerts_the_owner_once_an_hour_and_a_404_never_does(): void
    {
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $someone = User::factory()->create();

        $this->get('/_test/crash')->assertStatus(500);
        $this->get('/_test/crash')->assertStatus(500);
        $this->get('/_test/missing')->assertNotFound();

        $alerts = $owner->fresh()->notifications;
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('RuntimeException on GET /_test/crash', $alerts->first()->data['title']);
        $this->assertStringContainsString('Undefined variable $shipment', $alerts->first()->data['body']);
        $this->assertCount(0, $someone->fresh()->notifications);
    }

    public function test_alerts_are_off_outside_production_unless_turned_on(): void
    {
        config(['app.error_alerts' => null]);
        $this->assertFalse(ErrorAlert::enabled());
        config(['app.error_alerts' => 'true']);
        $this->assertTrue(ErrorAlert::enabled());
        $this->assertFalse(ErrorAlert::shouldSend(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException()));
        $this->assertTrue(ErrorAlert::shouldSend(new \ErrorException('boom')));
        // A deploy rebuilding its route cache is not a crash.
        $this->assertFalse(ErrorAlert::shouldSend(new \ErrorException('require(/var/www/vortexops/bootstrap/cache/routes-v7.php): Failed to open stream: No such file or directory')));
    }
}
