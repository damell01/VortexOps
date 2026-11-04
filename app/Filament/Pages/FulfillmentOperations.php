<?php

namespace App\Filament\Pages;

use App\Services\FulfillmentFlagService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class FulfillmentOperations extends Page
{
    protected static ?string $title = 'Fulfillment Operations';
    protected static ?string $slug = 'fulfillment-operations';
    protected static bool $shouldRegisterNavigation = false;

    public string $resolution = '';
    public ?int $selectedFlag = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-operations';
    }

    public function resolveFlag(int $flagId): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['resolution' => ['required', 'string', 'min:3', 'max:2000']]);
        app(FulfillmentFlagService::class)->resolve(auth()->user(), $flagId, $this->resolution);
        $this->reset('resolution', 'selectedFlag');
        Notification::make()->title('Issue resolved')->success()->send();
    }

    public function metrics(): array
    {
        abort_unless(static::canAccess(), 403);
        $runs = DB::table('fulfillment_runs')->where('status', 'packing')->count();
        $packers = DB::table('fulfillment_run_packers')
            ->join('fulfillment_runs', 'fulfillment_runs.id', '=', 'fulfillment_run_packers.run_id')
            ->where('fulfillment_runs.status', 'packing')->distinct()->count('fulfillment_run_packers.user_id');
        $waiting = DB::table('fulfillment_run_buyers')->whereIn('run_id',
            DB::table('fulfillment_runs')->where('status', 'packing')->select('id'))
            ->where('status', 'waiting')->count();
        $open = DB::table('fulfillment_flags')->where('status', 'open')->count();
        $flags = DB::table('fulfillment_flags')->where('status', 'open')
            ->orderByDesc('created_at')->limit(50)->get();
        $stations = DB::table('fulfillment_stations as stations')
            ->leftJoin('fulfillment_runs as runs', function ($join) {
                $join->on('runs.station_occupied_id', '=', 'stations.id')->where('runs.status', '=', 'packing');
            })
            ->select('stations.name', 'stations.active', 'runs.id as run_id', 'runs.show_id', 'runs.started_at')
            ->orderBy('stations.sort')->get();
        $productivity = DB::table('fulfillment_run_buyers as buyers')
            ->join('users', 'users.id', '=', 'buyers.packed_by')
            ->whereNotNull('buyers.packed_at')
            ->where('buyers.packed_at', '>=', now()->subDays(7))
            ->select('users.name', DB::raw('COUNT(*) as buyers_packed'), DB::raw('SUM(buyers.item_count) as items_packed'))
            ->groupBy('users.id', 'users.name')->orderByDesc('buyers_packed')->limit(20)->get();
        $fulfillmentUserIds = \App\Models\User::role(['fulfillment', 'fulfillment_admin'])->select('id');
        $clockedIn = \App\Models\TimeEntry::query()
            ->whereIn('user_id', $fulfillmentUserIds)
            ->whereNull('clocked_out_at')->count();
        $hoursPendingReview = \App\Models\TimeEntry::query()
            ->whereIn('user_id', \App\Models\User::role(['fulfillment', 'fulfillment_admin'])->select('id'))
            ->where('status', 'flagged')->count();
        $missingPayRates = \App\Models\Streamer::query()->where('member_type', 'fulfillment')
            ->where('status', 'active')->get()
            ->filter(fn ($member) => $member->paidHourlyRate() === null)->count();
        return compact('runs', 'packers', 'waiting', 'open', 'flags', 'stations', 'productivity', 'clockedIn', 'hoursPendingReview', 'missingPayRates');
    }
}
