<?php

namespace Tests\Feature\Notifications;

use App\Models\Show;
use App\Models\User;
use App\Notifications\MidweekReportNotification;
use App\Notifications\WeeklyReviewReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->creator = User::factory()->create();
    }

    private function makeShow(array $attrs = []): Show
    {
        return Show::create(array_merge([
            'title'      => 'Test Show',
            'show_date'  => now()->toDateString(),
            'status'     => 'draft',
            'created_by' => $this->creator->id,
        ], $attrs));
    }

    // ── Weekly review reminder ──────────────────────────────────────────────

    public function test_weekly_review_reminder_notifies_admins_when_shows_pending(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'pending_review']);
        $this->makeShow(['status' => 'pending_approval']);

        $this->artisan('reports:weekly-review-reminder')->assertSuccessful();

        Notification::assertSentTo(
            $this->admin,
            WeeklyReviewReminderNotification::class,
            fn (WeeklyReviewReminderNotification $n) => $n->pendingCount === 2,
        );
    }

    public function test_weekly_review_reminder_skips_when_nothing_pending(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'reconciled']);

        $this->artisan('reports:weekly-review-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_weekly_review_reminder_ignores_shows_outside_the_week(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'pending_review', 'show_date' => now()->subWeeks(2)->toDateString()]);

        $this->artisan('reports:weekly-review-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ── Mid-week report ──────────────────────────────────────────────────────

    public function test_midweek_report_notifies_admins_with_week_stats(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'reconciled', 'gross_revenue' => 500, 'units_sold' => 10]);
        $this->makeShow(['status' => 'reconciled', 'gross_revenue' => 250, 'units_sold' => 5]);

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertSentTo(
            $this->admin,
            MidweekReportNotification::class,
            fn (MidweekReportNotification $n) => $n->showCount === 2
                && (float) $n->grossRevenue === 750.0
                && $n->unitsSold === 15,
        );
    }

    public function test_midweek_report_skips_when_no_shows_this_week(): void
    {
        Notification::fake();

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_midweek_report_excludes_cancelled_shows(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'cancelled', 'gross_revenue' => 500, 'units_sold' => 10]);

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
