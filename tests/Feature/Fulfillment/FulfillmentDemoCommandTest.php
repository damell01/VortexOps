<?php

namespace Tests\Feature\Fulfillment;

use App\Models\FulfillmentFlag;
use App\Models\FulfillmentRun;
use App\Models\FulfillmentShift;
use App\Models\Setting;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FulfillmentDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_a_full_demo_without_notifying_anyone_and_removes_only_its_own_data(): void
    {
        Notification::fake();
        $real = Show::create(['title' => 'Real show', 'show_date' => today()->toDateString(), 'status' => 'draft']);

        $this->artisan('fulfillment:demo')->assertSuccessful();

        $statuses = FulfillmentRun::query()->pluck('status')->countBy()->all();
        $this->assertSame(2, $statuses['packing'] ?? 0);
        $this->assertSame(1, $statuses['paused'] ?? 0);
        $this->assertSame(1, $statuses['completed'] ?? 0);
        $this->assertSame(1, $statuses['approved'] ?? 0);
        $this->assertGreaterThanOrEqual(2, $statuses['ready'] ?? 0);
        $this->assertSame(1, FulfillmentFlag::query()->where('status', 'open')->count());
        $this->assertSame(15, FulfillmentShift::query()->count());
        $this->assertSame(7, Show::query()->where('title', 'like', 'DEMO · %')->count());
        $this->assertGreaterThan(0, DB::table('fulfillment_events')->count());
        Notification::assertNothingSent();

        // The whiteboard and pages work on it.
        $this->assertNotEmpty(app(\App\Http\Controllers\FulfillmentWhiteboardController::class)->payload()['activity']);

        // A refresh while it is fresh changes nothing.
        $ids = Show::query()->where('title', 'like', 'DEMO · %')->pluck('id')->all();
        $this->artisan('fulfillment:demo --refresh')->expectsOutputToContain('up to date')->assertSuccessful();
        $this->assertSame($ids, Show::query()->where('title', 'like', 'DEMO · %')->pluck('id')->all());

        // ...but a stale demo is rebuilt.
        Setting::set('fulfillment_demo', json_encode(array_merge(json_decode(Setting::get('fulfillment_demo'), true), ['created_at' => now()->subDays(4)->toIso8601String()])));
        $this->artisan('fulfillment:demo --refresh')->expectsOutputToContain('ready')->assertSuccessful();
        $this->assertNotSame($ids, Show::query()->where('title', 'like', 'DEMO · %')->pluck('id')->all());

        $this->artisan('fulfillment:demo --remove')->assertSuccessful();
        $this->assertSame(0, Show::query()->where('title', 'like', 'DEMO · %')->count());
        $this->assertSame(0, FulfillmentRun::query()->count());
        $this->assertSame(0, FulfillmentShift::query()->count());
        $this->assertTrue(Show::query()->whereKey($real->id)->exists());
    }

    public function test_never_runs_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('fulfillment:demo')->expectsOutputToContain('never created in production')->assertFailed();
        $this->assertSame(0, Show::query()->count());
    }
}
