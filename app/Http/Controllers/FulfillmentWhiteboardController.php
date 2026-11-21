<?php

namespace App\Http\Controllers;

use App\Models\FulfillmentRun;
use App\Models\FulfillmentStation;
use App\Services\FulfillmentFlagService;
use App\Support\BusinessTime;
use App\Support\FulfillmentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The TV whiteboard: a read-only, full-screen view of every station (or one),
 * replacing the physical whiteboards. The page polls a small JSON feed and
 * redraws itself, so a dropped network or a long night just reconnects.
 *
 * Shared screens show only what a whiteboard showed: show, station, packer,
 * times, progress and the current buyer's letter and username. No pay, no
 * order values, no addresses.
 */
class FulfillmentWhiteboardController extends Controller
{
    public function show(Request $request)
    {
        abort_unless(FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember($request->user()), 403);
        $station = $request->integer('station') ?: null;
        if ($station) abort_unless(FulfillmentStation::query()->whereKey($station)->exists(), 404);

        return view('fulfillment.whiteboard', [
            'station' => $station,
            'stationList' => FulfillmentStation::query()->where('active', true)->orderBy('sort')->orderBy('id')->get(['id', 'name']),
            'density' => in_array($request->query('density'), ['compact', 'comfortable', 'large', 'rows'], true) ? $request->query('density') : 'comfortable',
            'feedUrl' => route('fulfillment.whiteboard.feed', array_filter(['station' => $station])),
            'initial' => ['v' => \App\Support\FulfillmentPulse::version()] + $this->payload($station),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        abort_unless(FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember($request->user()), 403);

        // The screen sends the version it last drew; when nothing has changed it
        // gets a few bytes back instead of the whole board.
        $v = \App\Support\FulfillmentPulse::version(); // read before the board is built, so nothing slips between
        if ($v !== '0' && $request->query('since') === $v) {
            return response()->json(['same' => true, 'v' => $v])->header('Cache-Control', 'no-store');
        }

        return response()->json(['v' => $v] + $this->payload($request->integer('station') ?: null))->header('Cache-Control', 'no-store');
    }

    /** Only what a shared screen may show. */
    public function payload(?int $only = null): array
    {
        $tz = BusinessTime::zone();
        $stations = FulfillmentStation::query()->where('active', true)->when($only, fn ($q) => $q->whereKey($only))
            ->orderBy('sort')->orderBy('id')->get(['id', 'name']);
        $runs = FulfillmentRun::query()
            ->where(fn ($q) => $q->whereIn('station_occupied_id', $stations->pluck('id'))
                ->orWhere(fn ($r) => $r->where('status', FulfillmentRun::READY)->whereIn('station_id', $stations->pluck('id'))))
            ->with(['show:id,title,show_date,start_time', 'packers:id,name', 'currentBuyer:id,letter,buyer_username,package_count', 'show.fulfillmentUsers:id,name'])
            ->get();
        $buyerRows = DB::table('fulfillment_run_buyers')->whereIn('run_id', $runs->pluck('id'))
            ->orderBy('run_id')->orderByRaw('LOWER(buyer_username)')->orderBy('id')
            ->get(['id', 'run_id', 'letter', 'buyer_username', 'status', 'package_count'])->groupBy('run_id');
        // One batched query each for issues and activity, regardless of station count.
        $openIssues = DB::table('fulfillment_flags')->whereIn('run_id', $runs->pluck('id'))
            ->where('status', 'open')->orderBy('id')->get(['run_id', 'type'])->groupBy('run_id');
        $lastActions = DB::table('fulfillment_events')->whereIn('run_id', $runs->pluck('id'))
            ->select('run_id')->selectRaw('MAX(created_at) AS last_at')->groupBy('run_id')->pluck('last_at', 'run_id');
        $todayStart = BusinessTime::local()->startOfDay()->utc();

        return [
            'now' => BusinessTime::local()->format('M j, Y · g:i A'),
            'server_time' => now()->toIso8601String(),
            'summary' => [
                'packing' => $runs->filter(fn ($r) => $r->isOnStation())->count(),
                'stations' => FulfillmentStation::query()->where('active', true)->count(),
                'packed_today' => DB::table('fulfillment_run_packages')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count(),
                'buyers_today' => DB::table('fulfillment_run_buyers')->where('status', 'packed')->where('packed_at', '>=', $todayStart)->count(),
                'issues' => DB::table('fulfillment_flags')->where('status', 'open')->count(),
            ],
            'activity' => $this->activity($tz, $only),
            'stations' => $stations->map(function (FulfillmentStation $station) use ($runs, $buyerRows, $openIssues, $lastActions, $tz) {
                $run = $runs->first(fn ($r) => (int) $r->station_occupied_id === $station->id)
                    ?? $runs->first(fn ($r) => $r->status === FulfillmentRun::READY && (int) $r->station_id === $station->id);
                if (! $run) return ['id' => $station->id, 'name' => $station->name, 'status' => 'idle'];
                $buyers = $buyerRows->get($run->id, collect())->values();
                $byPackages = $run->expected_packages !== null;
                $done = $byPackages ? (int) $run->completed_packages : $buyers->where('status', 'packed')->count();
                $total = $byPackages ? (int) $run->expected_packages : (int) $run->buyer_count;
                $onStation = $run->isOnStation();
                $currentIndex = $onStation && $run->current_buyer_id ? $buyers->search(fn ($b) => (int) $b->id === (int) $run->current_buyer_id) : false;
                $next = $buyers->slice($currentIndex === false ? 0 : $currentIndex + 1)
                    ->reject(fn ($b) => in_array($b->status, ['packed', 'held'], true))->take(5)
                    ->map(fn ($b) => ['letter' => $b->letter, 'buyer' => $b->buyer_username, 'packages' => $b->package_count === null ? null : (int) $b->package_count])->values()->all();
                $people = $run->packers->isNotEmpty() ? $run->packers : ($run->show?->fulfillmentUsers ?? collect());
                $issues = $openIssues->get($run->id, collect());
                $active = $onStation ? $run->activeSeconds() : null;
                $remaining = $total ? max(0, $total - $done) : null;
                // A pace based on only a few seconds is misleading. Require five
                // minutes of active packing and at least one completed unit.
                $pace = $active >= 300 && $done > 0 ? round($done * 3600 / $active, 1) : null;
                $lastAction = $lastActions->get($run->id);

                return [
                    'id' => $station->id, 'name' => $station->name, 'status' => $run->status,
                    'issue' => $issues->isNotEmpty(),
                    'issue_count' => $issues->count(),
                    'issue_label' => $issues->isNotEmpty() ? FulfillmentFlagService::label($issues->first()->type) : null,
                    'show' => $run->show?->title,
                    'stream' => trim(($run->show?->show_date?->format('M j') ?? '') . ($run->show?->start_time ? ', ' . $run->show->start_time->timezone($tz)->format('g:i A') : '')),
                    'started' => $run->started_at?->timezone($tz)->format('M j, g:i A'),
                    // Active packing time so far (pauses excluded), for the live timer.
                    'elapsed' => $active,
                    'wall_seconds' => $onStation && $run->started_at ? max(0, (int) $run->started_at->diffInSeconds(now())) : null,
                    'last_activity_at' => $lastAction ? Carbon::parse($lastAction)->toIso8601String() : $run->started_at?->toIso8601String(),
                    'ticking' => $run->status === FulfillmentRun::PACKING,
                    'pause_reason' => $run->status === FulfillmentRun::PAUSED ? FulfillmentRun::pauseReasonLabel($run->pause_reason) : null,
                    'packer' => $people->pluck('name')->map(fn ($n) => explode(' ', trim($n))[0])->join(', ') ?: null,
                    'initials' => $people->take(3)->map(fn ($u) => mb_strtoupper(mb_substr(trim($u->name), 0, 1)))->values()->all(),
                    'letter' => $onStation ? $run->currentBuyer?->letter : null,
                    'buyer' => $onStation ? $run->currentBuyer?->buyer_username : null,
                    'current_packages' => $onStation ? $run->currentBuyer?->package_count : null,
                    'next' => $onStation ? $next : [],
                    'buyers_done' => $buyers->where('status', 'packed')->count(),
                    'buyers_total' => $buyers->count() ?: null,
                    'done' => $done, 'total' => $total ?: null, 'remaining' => $remaining,
                    'pace' => $pace, 'eta_seconds' => $run->status === FulfillmentRun::PACKING && $pace && $remaining !== null ? (int) round($remaining * 3600 / $pace) : null,
                    'unit' => $byPackages ? 'packages' : 'buyers',
                    'pct' => $total ? min(100, (int) round($done / $total * 100)) : 0,
                ];
            })->values()->all(),
        ];
    }

    /** Recent packing moments for the ticker: station, letter, username and what happened. */
    private function activity(string $tz, ?int $only): array
    {
        return \App\Models\FulfillmentEvent::query()
            ->whereIn('type', ['buyer_packed', 'started', 'finished', 'issue_flagged', 'issue_resolved', 'paused', 'resumed'])
            ->where('created_at', '>=', now()->subHours(12))
            ->when($only, fn ($q) => $q->where('station_id', $only))
            ->with(['station:id,name', 'show:id,title'])
            ->latest('id')->limit(12)->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'at' => $e->created_at?->timezone($tz)->format('g:i A'),
                'station' => $e->station?->name,
                'letter' => $e->buyer_letter,
                'buyer' => $e->buyer_username,
                'show' => $e->type === 'finished' ? $e->show?->title : null,
                'packages' => $e->type === 'finished' ? ($e->data['packages'] ?? null) : null,
                'text' => match ($e->type) {
                    'buyer_packed' => 'packed',
                    'started' => 'started packing',
                    'finished' => 'finished the show',
                    'issue_flagged' => 'flagged an issue',
                    'issue_resolved' => 'issue resolved',
                    'paused' => 'paused' . (isset($e->data['reason']) ? ' · ' . $e->data['reason'] : ''),
                    'resumed' => 'resumed',
                    default => \App\Models\FulfillmentEvent::label($e->type),
                },
            ])->all();
    }

    /** A printable copy of a show's tracking record. Never required, always available. */
    public function print(Request $request, FulfillmentRun $run)
    {
        $user = $request->user();
        abort_unless(FulfillmentAccess::isManager($user) || FulfillmentAccess::canWorkRun($user, $run->id), 403);
        $run->load(['show:id,title,show_date,start_time,whatnot_channel_id', 'show.channel:id,name', 'show.streamers:id,name', 'station:id,name,camera_label',
            'packers:id,name', 'acknowledger:id,name', 'approver:id,name', 'buyers.packer:id,name', 'flags.reporter:id,name']);

        return view('fulfillment.print', ['run' => $run, 'tz' => BusinessTime::zone()]);
    }
}
