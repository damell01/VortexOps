<?php

namespace App\Support;

use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Notifications\DeductionApprovedNotification;
use App\Notifications\MidweekReportNotification;
use App\Notifications\PayoutProcessedNotification;
use App\Notifications\ShowPendingApprovalNotification;
use App\Notifications\ShowReadyNotification;
use App\Notifications\ShowReconciledNotification;
use App\Notifications\SystemHealthAlert;
use App\Notifications\VortexAlert;
use App\Notifications\WeeklyReviewReminderNotification;
use Illuminate\Notifications\Notification;

/**
 * A realistic example of each catalogued notification, for the preview and
 * "Send test" buttons in Settings → Notifications. Uses the latest real
 * record where the email needs one, so a test reads like the real thing;
 * an unsaved stand-in otherwise. Nothing is written.
 */
class NotificationSamples
{
    public static function make(string $event): Notification
    {
        $show = Show::query()->latest('id')->first()
            ?? (new Show)->forceFill(['id' => 0, 'title' => 'Sample Show — Friday Night Breaks', 'show_date' => now()->toDateString()]);
        $title = $show->title ?: 'Sample show';
        $showUrl = $show->exists ? \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) : url('/admin');

        $alert = fn (string $t, string $b, string $tone = 'info', array $links = []) => new VortexAlert($event, $t, $b, $tone, $links ?: ['Open VortexOps' => $showUrl]);

        return match ($event) {
            'show_ready' => new ShowReadyNotification($show),
            'show_pending_approval' => new ShowPendingApprovalNotification($show),
            'show_reconciled' => new ShowReconciledNotification($show),
            'payout_processed' => new PayoutProcessedNotification(
                Payout::with(['streamer', 'show'])->latest('id')->first()
                    ?? (new Payout)->forceFill(['id' => 0, 'calculated_payout' => 125.50])->setRelation('streamer', new Streamer(['name' => 'Sample Streamer']))
            ),
            'deduction_approved' => new DeductionApprovedNotification(
                DeductionRequest::with(['show', 'lines'])->latest('id')->first()
                    ?? (new DeductionRequest)->forceFill(['id' => 0])
            ),
            'weekly_review_reminder' => new WeeklyReviewReminderNotification(3, now()->subWeek()->startOfWeek()->format('M j').' – '.now()->subWeek()->endOfWeek()->format('M j'), $showUrl, null),
            'midweek_report' => new MidweekReportNotification(now()->startOfWeek()->format('M j').' – '.now()->endOfWeek()->format('M j'), 6, 18420.0, 512, $showUrl, 104.0, null),
            'system_health' => new SystemHealthAlert(['Sample: queue worker heartbeat is 12 minutes old', 'Sample: 2 failed jobs in the last hour']),
            'approved_data_changed' => $alert('Whatnot data changed after approval', "{$title}: Gross Revenue changed after approval. Review Show Activity if the change affects operations or payout reporting.", 'warning'),
            'report_submission_requested' => $alert('Show report requested', "Please submit the end-of-stream report for \"{$title}\".", 'warning'),
            'report_submitted' => $alert('Show report submitted', "{$title} is ready for admin approval.", 'warning'),
            'report_reviewed' => $alert('Changes Requested on Your Show Report', "Your show report for {$title} needs revision.\n\nReason: Two items are missing a location.", 'warning'),
            'report_reopen_requested' => $alert('A streamer wants to change a filed report', "{$title} — forgot to add the giveaway packs", 'warning'),
            'report_items_added' => $alert('Items added to your show report', "Items have been added to the log for \"{$title}\"", 'success'),
            'low_stock' => $alert('Low Stock: Sample Booster Box', '3 units remaining (reorder at 10)', 'warning'),
            'damaged' => $alert('Items Marked Damaged', '2x Sample Booster Box moved to damaged from A Main Warehouse', 'danger'),
            'ai_manifest' => $alert('AI manifest ready for review', 'Pallet PO-1042: 48 manifest lines added · 41 existing items preselected · 7 need review.', 'success'),
            'fulfillment_assigned' => $alert('Fulfillment show assigned', "You are assigned to fulfillment for {$title}. Open the Fulfillment Center to work its shipment and packing queue."),
            default => $alert(NotificationCatalog::label($event), 'This is a sample of this notification.'),
        };
    }
}
