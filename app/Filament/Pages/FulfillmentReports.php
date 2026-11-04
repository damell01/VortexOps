<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FulfillmentReports extends Page
{
    protected static ?string $title = 'Fulfillment Reports';
    protected static ?string $slug = 'fulfillment-reports';
    protected static bool $shouldRegisterNavigation = false;

    public int $days = 30;
    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin()
            || $user->isFulfillmentAdmin() || $user->isFulfillment()));
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-reports';
    }

    public function isManager(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function reportData(): array
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['days' => ['required', 'integer', Rule::in([7, 30, 90])]]);
        $since = now()->subDays($this->days);
        $manager = $this->isManager();
        $actorId = auth()->id();

        $packed = DB::table('fulfillment_run_buyers as buyers')
            ->join('fulfillment_runs as runs', 'runs.id', '=', 'buyers.run_id')
            ->join('shows', 'shows.id', '=', 'runs.show_id')
            ->whereNotNull('buyers.packed_at')->where('buyers.packed_at', '>=', $since)
            ->when(! $manager, fn ($q) => $q->where('buyers.packed_by', $actorId));

        $totals = (clone $packed)->selectRaw('COUNT(*) as buyers_packed, COALESCE(SUM(buyers.item_count),0) as items_packed, COUNT(DISTINCT runs.id) as runs_worked')->first();

        $daily = (clone $packed)->selectRaw('DATE(buyers.packed_at) as day, COUNT(*) as buyers_packed, COALESCE(SUM(buyers.item_count),0) as items_packed')
            ->groupByRaw('DATE(buyers.packed_at)')->orderBy('day')->get();

        $recent = (clone $packed)->select('buyers.buyer_username', 'buyers.letter', 'buyers.item_count',
                'buyers.packed_at', 'shows.title as show_title', 'runs.id as run_id')
            ->orderByDesc('buyers.packed_at')->limit(75)->get();

        $scans = DB::table('fulfillment_verifications as verifications')
            ->join('fulfillment_runs as runs', 'runs.id', '=', 'verifications.run_id')
            ->where('verifications.created_at', '>=', $since)
            ->when(! $manager, fn ($q) => $q->where('verifications.verified_by', $actorId))
            ->selectRaw("SUM(CASE WHEN verifications.result = 'matched' THEN verifications.quantity ELSE 0 END) as matched_units, SUM(CASE WHEN verifications.result = 'unmatched' THEN 1 ELSE 0 END) as rejected_scans")
            ->first();

        $issues = DB::table('fulfillment_flags')
            ->where('created_at', '>=', $since)
            ->when(! $manager, fn ($q) => $q->where('reported_by', $actorId))
            ->count();

        $leaderboard = collect();
        if ($manager) {
            $leaderboard = (clone $packed)->join('users', 'users.id', '=', 'buyers.packed_by')
                ->select('users.name', DB::raw('COUNT(*) as buyers_packed'),
                    DB::raw('COALESCE(SUM(buyers.item_count),0) as items_packed'))
                ->groupBy('users.id', 'users.name')->orderByDesc('buyers_packed')->limit(30)->get();
        }

        return compact('totals', 'daily', 'recent', 'scans', 'issues', 'leaderboard', 'manager');
    }
}
