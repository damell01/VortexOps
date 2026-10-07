<?php

namespace App\Support;

/**
 * Every notification VortexOps can send, in one place.
 *
 * Each event names who gets it by default and on which channels. Admins
 * override that per event in Settings → Notifications (stored by
 * NotificationRouter); each person can then turn off what reaches them in
 * their profile, except in-app items that are marked `locked` — work only
 * that person can do, like a report they have been asked to file.
 *
 * Audience keys: owner, admins (admin + super admin + owner), super_admins,
 * fulfillment (fulfillment + fulfillment admin), streamers, everyone, or
 * role:<name> for any other role. `involved` means the people the event is
 * about — the show's streamer, whoever started an import — and is resolved by
 * the code that sends it.
 */
class NotificationCatalog
{
    public const LEVELS = [
        'action' => 'Action needed',
        'alert' => 'Alert',
        'fyi' => 'FYI',
        'digest' => 'Summary',
    ];

    public const GROUPS = ['Shows', 'Reports', 'Inventory', 'Fulfillment', 'Payroll', 'Summaries', 'System'];

    public static function events(): array
    {
        return [
            // ── Shows ────────────────────────────────────────────────────
            'show_ready' => [
                'group' => 'Shows', 'label' => 'Show ready for review', 'level' => 'action',
                'description' => 'A new show is ready for streamer assignment and item mapping.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => true,
            ],
            'show_pending_approval' => [
                'group' => 'Shows', 'label' => 'Show waiting for approval', 'level' => 'action',
                'description' => 'A show has been mapped and is waiting for approval.',
                'roles' => ['admins'], 'involved' => true, 'involved_label' => "The show's streamer", 'involved_role' => 'streamer', 'in_app' => true, 'email' => true,
            ],
            'show_reconciled' => [
                'group' => 'Shows', 'label' => 'Show reconciled', 'level' => 'fyi',
                'description' => 'A show has been reconciled and closed out.',
                'roles' => ['admins'], 'involved' => true, 'involved_label' => "The show's streamer", 'involved_role' => 'streamer', 'in_app' => true, 'email' => false,
            ],
            'approved_data_changed' => [
                'group' => 'Shows', 'label' => 'Whatnot data changed after approval', 'level' => 'alert',
                'description' => 'A later Whatnot sync changed sales on a show that was already approved.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],

            // ── Reports ──────────────────────────────────────────────────
            'report_submission_requested' => [
                'group' => 'Reports', 'label' => 'Show report requested', 'level' => 'action', 'locked' => true,
                'description' => 'An admin has asked the streamer to file their end-of-stream report.',
                'roles' => [], 'involved' => true, 'involved_label' => "The show's streamer", 'involved_role' => 'streamer', 'in_app' => true, 'email' => true,
            ],
            'report_submitted' => [
                'group' => 'Reports', 'label' => 'Show report submitted', 'level' => 'action',
                'description' => 'A streamer filed a show report that needs review.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],
            'report_reviewed' => [
                'group' => 'Reports', 'label' => 'Your report was reviewed', 'level' => 'action', 'locked' => true,
                'description' => 'A report was approved, sent back for changes, reopened, or an edit request was declined.',
                'roles' => [], 'involved' => true, 'involved_label' => "The report's streamer", 'involved_role' => 'streamer', 'in_app' => true, 'email' => true,
            ],
            'report_reopen_requested' => [
                'group' => 'Reports', 'label' => 'Request to edit a filed report', 'level' => 'action',
                'description' => 'A streamer asked to change a report they already filed.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => true,
            ],
            'report_items_added' => [
                'group' => 'Reports', 'label' => 'Items added to a report', 'level' => 'fyi',
                'description' => 'Items were added to the streamer log for a show.',
                'roles' => [], 'involved' => true, 'involved_label' => "The show's streamer", 'involved_role' => 'streamer', 'in_app' => true, 'email' => false,
            ],

            // ── Inventory ────────────────────────────────────────────────
            'low_stock' => [
                'group' => 'Inventory', 'label' => 'Low stock', 'level' => 'alert',
                'description' => 'An item dropped to or below its reorder level.',
                'roles' => ['admins', 'fulfillment'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],
            'damaged' => [
                'group' => 'Inventory', 'label' => 'Items marked damaged', 'level' => 'alert',
                'description' => 'Stock was moved to a damaged location.',
                'roles' => ['admins', 'fulfillment'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],
            'ai_manifest' => [
                'group' => 'Inventory', 'label' => 'AI manifest finished', 'level' => 'fyi',
                'description' => 'An AI manifest import finished or failed.',
                'roles' => [], 'involved' => true, 'involved_label' => 'Whoever started it', 'in_app' => true, 'email' => false,
            ],

            // ── Fulfillment ──────────────────────────────────────────────
            'fulfillment_assigned' => [
                'group' => 'Fulfillment', 'label' => 'Fulfillment show assigned', 'level' => 'action',
                'description' => 'Someone was assigned to pack and ship a show.',
                'roles' => [], 'involved' => true, 'involved_label' => 'The assigned packer', 'involved_role' => 'fulfillment', 'in_app' => true, 'email' => true,
            ],

            // ── Payroll ──────────────────────────────────────────────────
            'payout_processed' => [
                'group' => 'Payroll', 'label' => 'Payout processed', 'level' => 'fyi',
                'description' => 'A streamer payout was processed.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],
            'deduction_approved' => [
                'group' => 'Payroll', 'label' => 'Deduction approved', 'level' => 'fyi',
                'description' => 'An inventory deduction request was approved.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => false,
            ],

            // ── Summaries ────────────────────────────────────────────────
            'weekly_review_reminder' => [
                'group' => 'Summaries', 'label' => 'Weekly review reminder', 'level' => 'digest',
                'description' => 'Monday reminder of shows from last week still waiting for review.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => true,
            ],
            'midweek_report' => [
                'group' => 'Summaries', 'label' => 'Midweek report', 'level' => 'digest',
                'description' => 'Midweek summary of sales and open work.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => true,
            ],

            // ── System ───────────────────────────────────────────────────
            'system_health' => [
                'group' => 'System', 'label' => 'System health problem', 'level' => 'alert',
                'description' => 'The queue, worker, disk or Whatnot import needs attention.',
                'roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => true,
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return static::events()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, static::events());
    }

    public static function label(string $key): string
    {
        return static::get($key)['label'] ?? ucwords(str_replace('_', ' ', $key));
    }

    /** @return array<string, array<string, array>> events grouped for display */
    public static function grouped(): array
    {
        $out = array_fill_keys(static::GROUPS, []);
        foreach (static::events() as $key => $event) $out[$event['group']][$key] = $event;
        return array_filter($out);
    }

    /** Fixed audience choices shown as checkboxes; any other role appears as role:<name>. */
    public static function audiences(): array
    {
        return [
            'owner' => 'Owner',
            'admins' => 'Admins',
            'super_admins' => 'Super admins',
            'fulfillment' => 'Fulfillment',
            'streamers' => 'Streamers',
            'everyone' => 'Everyone',
        ];
    }
}
