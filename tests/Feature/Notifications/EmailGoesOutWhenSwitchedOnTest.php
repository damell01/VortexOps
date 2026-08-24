<?php

namespace Tests\Feature\Notifications;

use App\Jobs\NotifyShowReady;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\ShowReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Configuring a mailer has to change what the app actually sends.
 *
 * Seven of the ten notifications were database-only and hardcoded that way, so
 * Resend could be wired perfectly and the only thing that ever arrived was a
 * badge on the bell in the panel. The three that did declare 'mail' are the
 * scheduled digests, which is why mail looked connected when it mostly was
 * not.
 *
 * Off by default on purpose: switching a mailer on should not silently start
 * emailing a team every operational event they were used to seeing in-app.
 */
class EmailGoesOutWhenSwitchedOnTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $this->admin = User::factory()->create(['email' => 'ops@example.com']);
        $this->admin->assignRole('admin');

        $this->show = Show::create([
            'whatnot_channel_id' => WhatnotChannel::create(['name' => 'Vortex', 'status' => 'active'])->id,
            'title'              => 'Break #12',
            'show_date'          => now()->subDay()->toDateString(),
        ]);
    }

    public function test_in_app_only_by_default(): void
    {
        $notification = new ShowReadyNotification($this->show);

        $this->assertSame(['database'], $notification->via($this->admin));
    }

    public function test_switching_it_on_adds_the_mail_channel(): void
    {
        Setting::set('notify_email_enabled', '1');

        $notification = new ShowReadyNotification($this->show);

        $this->assertSame(['database', 'mail'], $notification->via($this->admin));
    }

    public function test_the_string_zero_does_not_count_as_on(): void
    {
        // Settings are stored as strings and '0' is truthy in PHP, which is
        // exactly how a switch ends up permanently on.
        Setting::set('notify_email_enabled', '0');

        $this->assertFalse(ShowReadyNotification::emailIsEnabled());
    }

    public function test_the_notification_renders_a_real_email(): void
    {
        Setting::set('notify_email_enabled', '1');

        $mail = (new ShowReadyNotification($this->show))->toMail($this->admin);

        $this->assertStringContainsString('Break #12', $mail->subject);
        $this->assertNotEmpty($mail->actionUrl, 'the email has no way back into the app');
    }

    public function test_the_extra_settings_address_is_notified(): void
    {
        // A field on the Settings page took an email address and nothing ever
        // read it. It is for someone who needs the alert without a login.
        Setting::set('notify_email_enabled', '1');
        Setting::set('show_ready_notification_email', 'warehouse@example.com');

        Notification::fake();

        (new NotifyShowReady($this->show->id))->handle(app(\App\Services\NotificationRouter::class));

        Notification::assertSentOnDemand(
            ShowReadyNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'warehouse@example.com',
        );
    }

    public function test_the_extra_address_is_left_alone_while_email_is_off(): void
    {
        Setting::set('notify_email_enabled', '0');
        Setting::set('show_ready_notification_email', 'warehouse@example.com');

        Notification::fake();

        (new NotifyShowReady($this->show->id))->handle(app(\App\Services\NotificationRouter::class));

        Notification::assertNothingSentTo(
            (new \Illuminate\Notifications\AnonymousNotifiable)->route('mail', 'warehouse@example.com')
        );
    }

    public function test_the_admin_still_gets_the_in_app_one(): void
    {
        Notification::fake();

        (new NotifyShowReady($this->show->id))->handle(app(\App\Services\NotificationRouter::class));

        Notification::assertSentTo($this->admin, ShowReadyNotification::class);
    }

    public function test_mail_test_reports_a_missing_resend_key(): void
    {
        config(['mail.default' => 'resend', 'services.resend.key' => null]);

        $this->artisan('mail:test', ['email' => 'someone@example.com'])
            ->expectsOutputToContain('RESEND_API_KEY is empty')
            ->assertFailed();
    }

    public function test_mail_test_actually_sends(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['email' => 'someone@example.com'])->assertSuccessful();
    }

    public function test_a_missing_role_does_not_silence_every_notification(): void
    {
        // User::role() throws for a name with no row behind it, and every
        // caller wraps the dispatch in a try/catch that logs a warning. So a
        // renamed or deleted super_admin would have stopped admin
        // notifications application-wide, leaving nothing but a log line.
        Role::whereIn('name', ['super_admin'])->delete();

        $recipients = app(\App\Services\NotificationRouter::class)->getRecipients('show_ready');

        $this->assertTrue($recipients->contains('id', $this->admin->id));
    }

    public function test_no_admin_roles_at_all_returns_nobody_rather_than_throwing(): void
    {
        Role::query()->delete();

        $this->assertCount(0, app(\App\Services\NotificationRouter::class)->getRecipients('show_ready'));
    }
}
