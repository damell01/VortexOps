<?php

namespace App\Console\Commands;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Notifications\WeeklyReviewReminderNotification;
use App\Services\NotificationRouter;
use Illuminate\Console\Command;

/**
 * Friday reminder: notify ops when shows from the current week are still
 * sitting in Pending Review / Pending Approval, so nothing slips into next
 * week unreviewed.
 */
class SendWeeklyReviewReminder extends Command
{
    protected $signature = 'reports:weekly-review-reminder';

    protected $description = 'Email/notify admins about shows still needing review for the current week';

    public function handle(NotificationRouter $router): int
    {
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        $pendingCount = Show::whereIn('status', ['pending_review', 'pending_approval'])
            ->whereBetween('show_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->count();

        if ($pendingCount === 0) {
            $this->info('No shows pending review this week — nothing to send.');
            return self::SUCCESS;
        }

        $recipients = $router->getRecipients('weekly_review_reminder');
        $weekLabel  = $weekStart->format('M j') . ' – ' . $weekEnd->format('M j');
        $reviewUrl  = ShowResource::getUrl('index');

        foreach ($recipients as $user) {
            $user->notify(new WeeklyReviewReminderNotification($pendingCount, $weekLabel, $reviewUrl));
        }

        $this->info("Notified {$recipients->count()} user(s) about {$pendingCount} show(s) needing review.");

        return self::SUCCESS;
    }
}
