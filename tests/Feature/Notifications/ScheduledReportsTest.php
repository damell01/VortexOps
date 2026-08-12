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

    public function test_weekly_review_reminder_narrative_null_when_ai_module_off(): void
    {
        Notification::fake();
        $this->makeShow(['status' => 'pending_review']);

        $this->artisan('reports:weekly-review-reminder')->assertSuccessful();

        Notification::assertSentTo(
            $this->admin,
            WeeklyReviewReminderNotification::class,
            fn (WeeklyReviewReminderNotification $n) => $n->narrative === null,
        );
    }

    // The AI-narrative-enabled path (OllamaClient mocked and actually generating
    // text) is covered directly and in isolation by OpsDigestServiceTest — binding
    // a mock OllamaClient into the container here was found to corrupt Livewire's
    // test state for unrelated tests later in the same suite run.

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

    public function test_midweek_report_includes_pacing_percentage(): void
    {
        Notification::fake();

        $weekStart = now()->startOfWeek();
        $this->makeShow(['status' => 'reconciled', 'gross_revenue' => 500, 'show_date' => $weekStart->toDateString()]);
        for ($i = 1; $i <= 4; $i++) {
            $this->makeShow(['status' => 'reconciled', 'gross_revenue' => 100, 'show_date' => $weekStart->copy()->subWeeks($i)->toDateString()]);
        }

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertSentTo(
            $this->admin,
            MidweekReportNotification::class,
            fn (MidweekReportNotification $n) => $n->pacingPct !== null && $n->pacingPct > 0,
        );
    }

    public function test_midweek_report_narrative_null_when_ai_module_off(): void
    {
        Notification::fake();
        $this->makeShow(['status' => 'reconciled', 'gross_revenue' => 500, 'units_sold' => 10]);

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertSentTo(
            $this->admin,
            MidweekReportNotification::class,
            fn (MidweekReportNotification $n) => $n->narrative === null,
        );
    }

    // The AI-narrative-enabled path (OllamaClient mocked and actually generating
    // text) is covered directly and in isolation by OpsDigestServiceTest — binding
    // a mock OllamaClient into the container here was found to corrupt Livewire's
    // test state for unrelated tests later in the same suite run.

    public function test_midweek_report_excludes_cancelled_shows(): void
    {
        Notification::fake();

        $this->makeShow(['status' => 'cancelled', 'gross_revenue' => 500, 'units_sold' => 10]);

        $this->artisan('reports:midweek-report')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
