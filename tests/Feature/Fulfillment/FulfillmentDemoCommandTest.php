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
        $this->assertSame(16, FulfillmentShift::query()->count());
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

    public function test_demo_sign_ins_work_keep_their_password_and_the_worker_has_a_show_to_pack(): void
    {
        Notification::fake();
        $this->artisan('fulfillment:demo')->expectsOutputToContain('fulfillment-worker@vortexops.test')->assertSuccessful();

        $password = \Illuminate\Support\Facades\Crypt::decryptString(json_decode(Setting::get('fulfillment_demo_logins'), true)['password']);
        $admin = \App\Models\User::query()->where('email', 'fulfillment-admin@vortexops.test')->firstOrFail();
        $worker = \App\Models\User::query()->where('email', 'fulfillment-worker@vortexops.test')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $worker->password));
        $this->assertTrue(\App\Support\FulfillmentAccess::isManager($admin));
        $this->assertTrue(\App\Support\FulfillmentAccess::isMember($worker));
        $this->assertFalse(\App\Support\FulfillmentAccess::isManager($worker));

        // The worker's show is ready on a station, and they are on shift now.
        $run = FulfillmentRun::query()->whereHas('show', fn ($q) => $q->where('title', 'DEMO · Massive Warehouse'))->firstOrFail();
        $this->assertSame('ready', $run->status);
        $this->assertTrue(\App\Support\FulfillmentAccess::canWorkRun($worker, $run->id));
        $this->assertTrue(FulfillmentShift::query()->where('user_id', $worker->id)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->exists());
        $this->actingAs($worker)->get(\App\Filament\Pages\FulfillmentHome::getUrl())->assertOk()->assertSee('Massive Warehouse');
        $this->flushSession();
        $this->actingAs($admin)->get(\App\Filament\Pages\FulfillmentHome::getUrl())->assertOk()->assertSee('Saturday Full Send');

        // A rebuild keeps the password; deploy logs never show it.
        $this->artisan('fulfillment:demo --hide-password')->doesntExpectOutputToContain($password)->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $worker->fresh()->password));
        $this->artisan('fulfillment:demo --logins')->expectsOutputToContain($password)->assertSuccessful();

        $this->artisan('fulfillment:demo --refresh --reset-password')->assertSuccessful();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check($password, $worker->fresh()->password));

        // Removing the demo keeps the sign-ins.
        $this->artisan('fulfillment:demo --remove')->assertSuccessful();
        $this->assertTrue($worker->fresh() !== null);
    }

    public function test_never_runs_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('fulfillment:demo')->expectsOutputToContain('never created in production')->assertFailed();
        $this->assertSame(0, Show::query()->count());
    }
}
