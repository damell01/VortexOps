<?php

namespace App\Support;

/**
 * The small icon beside each menu link, desktop and phone, looked up by the
 * link's label so a link carries one without every menu entry naming it.
 * A link that names its own icon keeps it.
 */
class NavIcons
{
    public const ICONS = [
        // Shows
        'Shows Overview' => 'heroicon-o-tv',
        'Show Calendar' => 'heroicon-o-calendar-days',
        'Show Leaderboard' => 'heroicon-o-trophy',
        'Log a Show' => 'heroicon-o-pencil-square',
        'Channel Review' => 'heroicon-o-signal',
        'Stream Review' => 'heroicon-o-user-group',
        'Whatnot Sheet Check' => 'heroicon-o-table-cells',
        'Show Report Inbox' => 'heroicon-o-inbox-arrow-down',
        // Inventory
        'Overview' => 'heroicon-o-squares-2x2',
        'All Inventory' => 'heroicon-o-cube',
        'Receive Inventory' => 'heroicon-o-truck',
        'Inventory History' => 'heroicon-o-clock',
        'Locations & Vendors' => 'heroicon-o-map-pin',
        // Payroll
        'Payroll Overview' => 'heroicon-o-wallet',
        'Pay Run History' => 'heroicon-o-banknotes',
        'Streamer Loans' => 'heroicon-o-credit-card',
        'Payment Structures' => 'heroicon-o-adjustments-horizontal',
        'Businesses' => 'heroicon-o-building-office-2',
        'Payroll Simulator' => 'heroicon-o-calculator',
        // Timekeeping
        'Timekeeping' => 'heroicon-o-clock',
        // Admin
        'Users' => 'heroicon-o-users',
        'Roles & Permissions' => 'heroicon-o-shield-check',
        'Activity Logs' => 'heroicon-o-document-magnifying-glass',
        'Notifications' => 'heroicon-o-bell',
        'Email Log' => 'heroicon-o-envelope',
        'Settings' => 'heroicon-o-cog-6-tooth',
        // Owner tools
        'Messages & Announcements' => 'heroicon-o-chat-bubble-left-right',
        'Import Status' => 'heroicon-o-arrow-down-tray',
        'Show Data Audit' => 'heroicon-o-clipboard-document-check',
        'Show Ingestion Logs' => 'heroicon-o-queue-list',
        'Whatnot Scraper' => 'heroicon-o-arrow-path',
    ];

    public static function for(array $link): ?string
    {
        return $link[3] ?? self::ICONS[$link[0]] ?? null;
    }
}
