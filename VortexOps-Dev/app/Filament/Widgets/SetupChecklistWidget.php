<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AppSettings;
use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\StreamerResource;
use App\Filament\Resources\WhatnotChannelResource;
use App\Models\InventoryLocation;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

/**
 * First-run onboarding: a checklist that walks a fresh install through the
 * essential setup steps. It auto-hides once every step is done or the owner
 * dismisses it, so it never nags an established deployment.
 */
class SetupChecklistWidget extends Widget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = -10; // top of the dashboard
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.setup-checklist';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! (($user?->isAdmin() || $user?->isOwner()) ?? false)) {
            return false;
        }

        if ((bool) Setting::get('setup_checklist_dismissed', false)) {
            return false;
        }

        // Hide automatically once everything is set up.
        return collect((new self)->steps())->contains(fn ($s) => ! $s['done']);
    }

    protected function getViewData(): array
    {
        $steps = $this->steps();
        $done  = collect($steps)->where('done', true)->count();

        return [
            'steps'    => $steps,
            'doneCount' => $done,
            'total'    => count($steps),
        ];
    }

    public function dismiss(): void
    {
        Setting::set('setup_checklist_dismissed', true);
        $this->dispatch('$refresh');
    }

    /** @return array<int,array<string,mixed>> */
    public function steps(): array
    {
        $steps = [];

        try {
            if (AdminModules::isEnabled('streams')) {
                $steps[] = [
                    'label' => 'Connect a Whatnot channel',
                    'hint'  => 'Add the channel VortexOps imports shows from.',
                    'done'  => Schema::hasTable('whatnot_channels') && WhatnotChannel::query()->exists(),
                    'url'   => WhatnotChannelResource::getUrl('index'),
                ];

                $steps[] = [
                    'label' => 'Run your first import',
                    'hint'  => 'Pull past shows (and their orders) from Whatnot.',
                    'done'  => Schema::hasTable('shows') && Show::query()->exists(),
                    'url'   => WhatnotChannelResource::getUrl('index'),
                ];
            }

            if (AdminModules::isEnabled('streamers')) {
                $steps[] = [
                    'label' => 'Add your streamers',
                    'hint'  => 'Set up the people you run breaks with and their payout terms.',
                    'done'  => Schema::hasTable('streamers') && Streamer::query()->exists(),
                    'url'   => StreamerResource::getUrl('index'),
                ];
            }

            if (AdminModules::isEnabled('inventory')) {
                $steps[] = [
                    'label' => 'Create an inventory location',
                    'hint'  => 'Where stock lives — each streamer can have their own.',
                    'done'  => Schema::hasTable('inventory_locations') && InventoryLocation::query()->exists(),
                    'url'   => InventoryLocationResource::getUrl('index'),
                ];
            }

            $steps[] = [
                'label' => 'Set your branding',
                'hint'  => 'Name, colour, and logo for your workspace.',
                'done'  => (bool) Setting::get('brand_name') && Setting::get('brand_name') !== 'VortexOps',
                'url'   => AppSettings::getUrl(),
            ];
        } catch (\Throwable) {
            // Partial migration — return whatever we could evaluate.
        }

        return $steps;
    }
}
