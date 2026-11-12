<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WhatnotSyncMonitor extends Page
{
    protected static ?string $title = 'Whatnot Sync Monitor';
    protected static ?string $navigationLabel = 'Whatnot Sync';
    protected static ?string $slug = 'whatnot-sync-monitor';

    public static function canAccess(): bool { return auth()->user()?->isOwner() ?? false; }
    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-signal'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Super Admin'; }
    public static function getNavigationSort(): ?int { return 22; }

    public function mount(): void { abort_unless(static::canAccess(), 403); }
    public function getView(): string { return 'filament.pages.whatnot-sync-monitor'; }

    public function getChannelHealth(): array
    {
        $channels = DB::table('whatnot_api_connections')
            ->where('enabled', true)->where('sync_enabled', true)
            ->pluck('whatnot_channel_id');
        $health = DB::table('whatnot_sync_health')
            ->whereIn('whatnot_channel_id', $channels)->get()
            ->groupBy('whatnot_channel_id');
        return $channels->map(function ($id) use ($health) {
            $rows = ($health->get($id) ?? collect())->keyBy('mode');
            $fresh = $rows->get('fresh');
            $catchup = $rows->get('catchup');
            $age = $fresh?->last_success_at ? \Carbon\Carbon::parse($fresh->last_success_at)->diffInMinutes(now(), false) : null;
            return [
                'channel' => $id,
                'fresh' => $fresh,
                'catchup' => $catchup,
                'age' => $age === null ? null : max(0, round($age, 1)),
                'healthy' => $age !== null && $age <= 10 && (bool) ($fresh?->last_run_ok),
                'errors' => json_decode($fresh?->errors ?? '[]', true) ?: [],
            ];
        })->all();
    }
}
