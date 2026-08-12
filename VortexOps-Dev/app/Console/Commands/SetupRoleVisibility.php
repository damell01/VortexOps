<?php

namespace App\Console\Commands;

use App\Support\NavVisibility;
use Illuminate\Console\Command;

class SetupRoleVisibility extends Command
{
    protected $signature = 'setup:role-visibility';

    protected $description = 'Configure default page visibility for roles (streamer, fulfillment, etc)';

    public function handle(): int
    {
        $this->info('Setting up role visibility defaults...');

        // Find the actual role names in the database (case-sensitive)
        $streamerRole = \Spatie\Permission\Models\Role::where('name', 'like', '%streamer%')->first();
        $fulfillmentRole = \Spatie\Permission\Models\Role::where('name', 'like', '%fulfillment%')
            ->whereNot('name', 'fulfillment_admin')->first();
        $fulfillmentAdminRole = \Spatie\Permission\Models\Role::where('name', 'fulfillment_admin')->first();

        $streamerRoleName = $streamerRole?->name ?? 'streamer';
        $fulfillmentRoleName = $fulfillmentRole?->name ?? 'fulfillment';
        $fulfillmentAdminRoleName = $fulfillmentAdminRole?->name ?? 'fulfillment_admin';

        // Streamer role — only sees their own shows/payouts and inventory
        $streamerHidden = [
            'App\Filament\Resources\ShowResource',
            'App\Filament\Resources\WhatnotChannelResource',
            'App\Filament\Resources\StreamerResource',
            'App\Filament\Resources\StreamerLoanResource',
            'App\Filament\Resources\FulfillmentResource',
            'App\Filament\Resources\FulfillmentCenterResource',
            'App\Filament\Resources\ReportResource',
            'App\Filament\Resources\UserResource',
            'App\Filament\Resources\VendorResource',
            'App\Filament\Resources\DeductionRequestResource',
            'App\Filament\Pages\AppSettings',
            'App\Filament\Pages\WhatnotBackfill',
            'App\Filament\Pages\StreamerAnalytics',
            'App\Filament\Pages\StreamerStatement',
            'App\Filament\Pages\ProfitSharePacket',
            'App\Filament\Pages\ProductInsights',
            'App\Filament\Pages\ShowStatusBoard',
            'App\Filament\Pages\DemoData',
            'App\Filament\Pages\LogViewer',
            'App\Filament\Pages\SystemHealth',
            'App\Filament\Pages\AiMonitoring',
            'App\Filament\Pages\HorizonDashboard',
        ];

        NavVisibility::setHiddenForRole($streamerRoleName, $streamerHidden);
        $this->line('✓ ' . $streamerRoleName . ' role: ' . count($streamerHidden) . ' pages hidden');

        // Fulfillment role — sees fulfillment center and shipments, limited inventory
        $fulfillmentHidden = [
            'App\Filament\Resources\ShowResource',
            'App\Filament\Resources\StreamerResource',
            'App\Filament\Resources\StreamerLoanResource',
            'App\Filament\Resources\StreamerLogResource',
            'App\Filament\Resources\PayoutResource',
            'App\Filament\Resources\WeeklyPayoutBatchResource',
            'App\Filament\Resources\WhatnotChannelResource',
            'App\Filament\Resources\VendorResource',
            'App\Filament\Resources\UserResource',
            'App\Filament\Resources\RoleResource',
            'App\Filament\Resources\PalletResource',
            'App\Filament\Resources\ReceiveSessionResource',
            'App\Filament\Resources\DeductionRequestResource',
            'App\Filament\Resources\ActivityLogResource',
            'App\Filament\Pages\AppSettings',
            'App\Filament\Pages\WhatnotBackfill',
            'App\Filament\Pages\StreamerAnalytics',
            'App\Filament\Pages\StreamerStatement',
            'App\Filament\Pages\ProfitSharePacket',
            'App\Filament\Pages\ProductInsights',
            'App\Filament\Pages\ShowStatusBoard',
            'App\Filament\Pages\DemoData',
            'App\Filament\Pages\LogViewer',
            'App\Filament\Pages\SystemHealth',
            'App\Filament\Pages\AiMonitoring',
            'App\Filament\Pages\HorizonDashboard',
        ];

        NavVisibility::setHiddenForRole($fulfillmentRoleName, $fulfillmentHidden);
        $this->line('✓ ' . $fulfillmentRoleName . ' role: ' . count($fulfillmentHidden) . ' pages hidden');

        // Admin role — full access to all pages
        NavVisibility::setHiddenForRole('admin', []);
        $this->line('✓ admin role: full access (sees all pages)');

        // Super admin role — full access to all pages
        NavVisibility::setHiddenForRole('super_admin', []);
        $this->line('✓ super_admin role: full access (sees all pages)');

        // Fulfillment admin role — like admin, sees everything (no hidden pages)
        NavVisibility::setHiddenForRole($fulfillmentAdminRoleName, []);
        $this->line('✓ ' . $fulfillmentAdminRoleName . ' role: full access (like admin)');

        // Clear memo so changes take effect immediately
        NavVisibility::flushMemo();

        $this->info('Role visibility configured successfully!');
        $this->line('Visit Settings → Roles & Permissions to adjust further.');

        return 0;
    }
}
