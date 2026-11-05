<?php

namespace App\Filament\Pages;

use App\Support\NavVisibility;
use Filament\Pages\Page;

/**
 * Owner Tools: one page of cards for the owner's settings and operational
 * tools (the mockup's "Owner Tools" screen). It only links to pages that
 * already exist, and each card is shown only when the owner can open that
 * page — the same checks the top navigation applies.
 */
class OwnerTools extends Page
{
    protected static ?string $title = 'Owner Tools';
    protected static ?string $navigationLabel = 'Owner Tools';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 90;
    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Owner Tools';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.owner-tools';
    }

    public function getSubheading(): ?string
    {
        return 'System settings, integrations and operational controls.';
    }

    /**
     * Tool cards, grouped. Each: [title, description, icon, page or resource class].
     *
     * @return array<string, list<array{title:string, text:string, icon:string, url:string}>>
     */
    public function sections(): array
    {
        $sections = [
            'Integrations' => [
                ['Whatnot API Control Center', 'Seller API connection, sync and backfill.', 'heroicon-o-signal', WhatnotApiDashboard::class],
                ['Whatnot API Changes', 'Review what the API changed on shows and orders.', 'heroicon-o-arrows-right-left', WhatnotApiChanges::class],
                ['Whatnot Scraper', 'Scraper runs, sessions and channels.', 'heroicon-o-cloud-arrow-down', WhatnotSyncPage::class],
                ['Import Status', 'Recent imports and their results.', 'heroicon-o-arrow-path', ImportStatus::class],
            ],
            'Streamers & communication' => [
                ['Streamer Login Notifications', 'Login popups and inventory alerts.', 'heroicon-o-bell-alert', InventoryPopupSettings::class],
                ['Messages & Announcements', 'Messages and announcements to the team.', 'heroicon-o-megaphone', Communications::class],
                ['Notifications', 'Who is notified about what.', 'heroicon-o-bell', NotificationSettings::class],
                ['Email Log', 'Emails the app has sent.', 'heroicon-o-envelope', EmailLog::class],
            ],
            'Data quality' => [
                ['Show Data Audit', 'Find and fix show data problems.', 'heroicon-o-clipboard-document-check', ShowDataAudit::class],
                ['Show Ingestion Logs', 'How each show was imported.', 'heroicon-o-document-text', \App\Filament\Resources\ShowIngestionLogResource::class],
                ['Activity Logs', 'Who changed what, and when.', 'heroicon-o-clock', \App\Filament\Resources\ActivityLogResource::class],
            ],
            'People & system' => [
                ['User Management', 'Accounts and access.', 'heroicon-o-users', \App\Filament\Resources\UserResource::class],
                ['Roles & Permissions', 'What each role can see and do.', 'heroicon-o-shield-check', \App\Filament\Resources\RoleResource::class],
                ['Businesses', 'Businesses, storage and settings.', 'heroicon-o-building-office-2', Businesses::class],
                ['Fulfillment Center', 'Shipping workflow and processing.', 'heroicon-o-truck', \App\Filament\Resources\FulfillmentResource::class],
                ['System Settings', 'Branding, fees and app settings.', 'heroicon-o-cog-6-tooth', AppSettings::class],
            ],
        ];

        $user = auth()->user();
        $out = [];
        foreach ($sections as $label => $tools) {
            foreach ($tools as [$title, $text, $icon, $class]) {
                if (! class_exists($class)) continue;
                try {
                    if (method_exists($class, 'canAccess') && ! $class::canAccess()) continue;
                    if (NavVisibility::isHiddenForUser($class, $user)) continue;
                    $url = is_subclass_of($class, \Filament\Resources\Resource::class)
                        ? $class::getUrl('index')
                        : $class::getUrl(panel: 'admin');
                } catch (\Throwable) {
                    continue;
                }
                $out[$label][] = compact('title', 'text', 'icon', 'url');
            }
        }

        return $out;
    }
}
