<?php

namespace Tests\Feature\Notifications;

use App\Filament\Pages\EmailLog;
use App\Filament\Pages\MyNotifications;
use App\Filament\Pages\NotificationSettings;
use App\Models\EmailLog as EmailLogModel;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\VortexAlert;
use App\Services\NotificationRouter;
use App\Services\Notifier;
use App\Support\NotificationCatalog;
use App\Support\NotificationSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One path for every notification: the admin's rule says who, the owner's
 * switches say whether email goes out at all, and each person can turn off
 * what reaches them — email always, in-app unless the item is Required.
 */
class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $streamer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'super_admin', 'streamer', 'fulfillment'] as $r) Role::findOrCreate($r, 'web');
        $this->owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->assignRole('admin');
        $this->streamer = User::factory()->create(['email' => 'jess@example.com']);
        $this->streamer->assignRole('streamer');

        config(['mail.notification_emails_enabled' => true, 'mail.default' => 'array']);
        Setting::set('notify_email_enabled', '1');
    }

    private function alert(string $event): VortexAlert
    {
        return new VortexAlert($event, 'Title', 'Body');
    }

    private function channels(string $event, User $user): array
    {
        return app(NotificationRouter::class)->channelsFor($event, $user->fresh());
    }

    public function test_admins_audience_includes_the_owner_and_admins_but_not_streamers(): void
    {
        $ids = app(NotificationRouter::class)->recipientsFor('report_submitted')->pluck('id');

        $this->assertTrue($ids->contains($this->owner->id));
        $this->assertTrue($ids->contains($this->admin->id));
        $this->assertFalse($ids->contains($this->streamer->id));
    }

    public function test_involved_people_are_added_only_when_the_rule_says_so(): void
    {
        $router = app(NotificationRouter::class);

        $this->assertTrue($router->recipientsFor('report_reviewed', [$this->streamer])->contains('id', $this->streamer->id));
        $this->assertFalse($router->recipientsFor('report_submitted', [$this->streamer])->contains('id', $this->streamer->id));
    }

    public function test_a_streamer_can_turn_off_email_for_one_event(): void
    {
        $this->assertSame(['database', 'mail'], $this->channels('report_reviewed', $this->streamer));

        $this->streamer->update(['notification_preferences' => ['report_reviewed' => ['in_app' => true, 'email' => false]]]);

        $this->assertSame(['database'], $this->channels('report_reviewed', $this->streamer));
    }

    public function test_required_items_stay_in_app_even_when_everything_is_paused(): void
    {
        $this->streamer->update(['notifications_enabled' => false]);

        $this->assertSame(['database'], $this->channels('report_reviewed', $this->streamer), 'locked event');
        $this->assertSame([], $this->channels('report_items_added', $this->streamer), 'ordinary event');
    }

    public function test_the_owner_switch_stops_all_email(): void
    {
        Setting::set('notify_email_enabled', '0');

        $this->assertSame(['database'], $this->channels('show_ready', $this->admin));
    }

    public function test_the_hourly_limit_holds_back_further_email(): void
    {
        Setting::set('notify_email_hourly_cap', '2');
        foreach (range(1, 2) as $i) EmailLogModel::create(['to_email' => $this->admin->email, 'subject' => "x{$i}", 'status' => 'sent']);

        $this->assertSame(['database'], $this->channels('show_ready', $this->admin));
    }

    public function test_an_admin_rule_changes_who_gets_it(): void
    {
        app(NotificationRouter::class)->saveRules(['low_stock' => ['roles' => [], 'users' => [$this->streamer->id], 'involved' => false, 'emails' => [], 'in_app' => true, 'email' => false, 'enabled' => true]]);

        $ids = app(NotificationRouter::class)->recipientsFor('low_stock')->pluck('id')->all();

        $this->assertSame([$this->streamer->id], $ids);
    }

    public function test_notifier_sends_and_the_email_is_logged_with_its_content(): void
    {
        Notifier::send('report_reviewed', $this->alert('report_reviewed'), [$this->streamer]);

        $log = EmailLogModel::where('to_email', 'jess@example.com')->first();
        $this->assertNotNull($log, 'the email was not recorded');
        $this->assertSame('sent', $log->status);
        $this->assertSame('report_reviewed', $log->event);
        $this->assertSame('Title', $log->subject);
        $this->assertStringContainsString('Manage your notifications', $log->html);
        $this->assertSame(1, $this->streamer->notifications()->count(), 'in-app copy');
    }

    public function test_extra_addresses_on_a_rule_get_the_email(): void
    {
        app(NotificationRouter::class)->saveRules(['show_ready' => ['roles' => [], 'users' => [], 'involved' => false, 'emails' => ['warehouse@example.com'], 'in_app' => true, 'email' => true]]);

        Notifier::send('show_ready', $this->alert('show_ready'));

        $this->assertTrue(EmailLogModel::where('to_email', 'warehouse@example.com')->where('status', 'sent')->exists());
    }

    public function test_every_catalogued_email_renders(): void
    {
        foreach (array_keys(NotificationCatalog::events()) as $key) {
            $html = (string) NotificationSamples::make($key)->toMail($this->owner)->render();
            $this->assertStringContainsString('Manage your notifications', $html, "{$key} email did not render with the branded layout");
        }
    }

    public function test_send_all_tests_emails_every_kind_and_marks_them(): void
    {
        Livewire::actingAs($this->owner)->test(NotificationSettings::class)
            ->set('testAddress', 'qa@example.com')
            ->call('sendAllTests')
            ->assertHasNoErrors();

        $this->assertSame(count(NotificationCatalog::events()), EmailLogModel::where('to_email', 'qa@example.com')->where('is_test', true)->where('status', 'sent')->count());
    }

    public function test_settings_page_saves_rules_and_only_the_owner_changes_the_global_switch(): void
    {
        Livewire::actingAs($this->admin)->test(NotificationSettings::class)
            ->set('rules.low_stock.roles', ['fulfillment'])
            ->set('rules.low_stock.email', true)
            ->set('emailEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $rule = app(NotificationRouter::class)->rule('low_stock');
        $this->assertSame(['fulfillment'], $rule['roles']);
        $this->assertTrue($rule['email']);
        $this->assertTrue(NotificationRouter::emailIsEnabled(), 'an admin turned off app email');

        Livewire::actingAs($this->owner)->test(NotificationSettings::class)->set('emailEnabled', false)->call('save');
        $this->assertFalse(NotificationRouter::emailIsEnabled());
    }

    public function test_preview_renders_the_email(): void
    {
        Livewire::actingAs($this->owner)->test(NotificationSettings::class)
            ->call('preview', 'low_stock')
            ->assertSet('previewEvent', 'low_stock')
            ->assertSee('Email preview');
    }

    public function test_my_notifications_lists_what_can_reach_a_streamer_and_saves_choices(): void
    {
        $page = Livewire::actingAs($this->streamer)->test(MyNotifications::class);
        $events = array_keys($page->instance()->events());

        $this->assertContains('report_reviewed', $events);
        $this->assertNotContains('report_submitted', $events, 'an admin-only event was offered to a streamer');

        $page->set('prefs.report_reviewed.email', false)->call('save');
        $this->assertFalse($this->streamer->fresh()->notificationPreference('report_reviewed')['email']);
    }

    public function test_email_log_page_opens_an_email(): void
    {
        $log = EmailLogModel::create(['to_email' => 'x@example.com', 'subject' => 'Hello there', 'html' => '<p>Body</p>', 'status' => 'sent', 'event' => 'low_stock']);

        Livewire::actingAs($this->owner)->test(EmailLog::class)
            ->assertSee('Hello there')
            ->call('open', $log->id)
            ->assertSee('Low stock');
    }

    public function test_streamers_cannot_open_the_admin_pages(): void
    {
        $this->actingAs($this->streamer);
        $this->assertFalse(NotificationSettings::canAccess());
        $this->assertFalse(EmailLog::canAccess());
        $this->assertTrue(MyNotifications::canAccess());
    }
}
