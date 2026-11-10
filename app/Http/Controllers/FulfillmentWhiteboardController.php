<?php

namespace App\Http\Controllers;

use App\Models\FulfillmentRun;
use App\Models\FulfillmentStation;
use App\Support\BusinessTime;
use App\Support\FulfillmentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'initial' => $this->payload($station),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        abort_unless(FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember($request->user()), 403);

        return response()->json($this->payload($request->integer('station') ?: null))->header('Cache-Control', 'no-store');
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
            ->with(['show:id,title,show_date,start_time', 'packers:id,name', 'currentBuyer:id,letter,buyer_username', 'show.fulfillmentUsers:id,name'])
            ->withCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->get();
        $buyerRows = DB::table('fulfillment_run_buyers')->whereIn('run_id', $runs->pluck('id'))
            ->orderBy('run_id')->orderByRaw('LOWER(buyer_username)')->orderBy('id')
            ->get(['id', 'run_id', 'letter', 'buyer_username', 'status'])->groupBy('run_id');
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
            'stations' => $stations->map(function (FulfillmentStation $station) use ($runs, $buyerRows, $tz) {
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
                    ->reject(fn ($b) => in_array($b->status, ['packed', 'held'], true))->take(3)
                    ->map(fn ($b) => ['letter' => $b->letter, 'buyer' => $b->buyer_username])->values()->all();
                $people = $run->packers->isNotEmpty() ? $run->packers : ($run->show?->fulfillmentUsers ?? collect());

                return [
                    'id' => $station->id, 'name' => $station->name, 'status' => $run->status,
                    'issue' => (int) $run->open_flags_count > 0,
                    'show' => $run->show?->title,
                    'stream' => trim(($run->show?->show_date?->format('M j') ?? '') . ($run->show?->start_time ? ', ' . $run->show->start_time->timezone($tz)->format('g:i A') : '')),
                    'started' => $run->started_at?->timezone($tz)->format('M j, g:i A'),
                    // Active packing time so far (pauses excluded), for the live timer.
                    'elapsed' => $onStation ? $run->activeSeconds() : null,
                    'ticking' => $run->status === FulfillmentRun::PACKING,
                    'packer' => $people->pluck('name')->map(fn ($n) => explode(' ', trim($n))[0])->join(', ') ?: null,
                    'initials' => $people->take(3)->map(fn ($u) => mb_strtoupper(mb_substr(trim($u->name), 0, 1)))->values()->all(),
                    'letter' => $onStation ? $run->currentBuyer?->letter : null,
                    'buyer' => $onStation ? $run->currentBuyer?->buyer_username : null,
                    'next' => $onStation ? $next : [],
                    'buyers_done' => $buyers->where('status', 'packed')->count(),
                    'buyers_total' => $buyers->count() ?: null,
                    'done' => $done, 'total' => $total ?: null, 'unit' => $byPackages ? 'packages' : 'buyers',
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
            ->with('station:id,name')
            ->latest('id')->limit(12)->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'at' => $e->created_at?->timezone($tz)->format('g:i A'),
                'station' => $e->station?->name,
                'letter' => $e->buyer_letter,
                'buyer' => $e->buyer_username,
                'text' => match ($e->type) {
                    'buyer_packed' => 'packed',
                    'started' => 'started packing',
                    'finished' => 'finished the show',
                    'issue_flagged' => 'flagged an issue',
                    'issue_resolved' => 'issue resolved',
                    'paused' => 'paused',
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
