<?php

namespace App\Filament\Pages;

use App\Models\FulfillmentRun;
use App\Models\FulfillmentRunBuyer;
use App\Models\FulfillmentStation;
use App\Services\FulfillmentFlagService;
use App\Services\FulfillmentQueue;
use App\Services\FulfillmentRunService;
use App\Services\FulfillmentVerificationService;
use App\Support\FulfillmentAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The worker's packing screen. Open a show, start packing at a station, and
 * work buyer by buyer: the letter is shown big for the camera, items are
 * scanned against the order, packages are marked packed. Everything is
 * recorded as it happens; nobody writes anything down.
 */
class FulfillmentStationBoard extends Page
{
    /** The page draws its own header. */
    public function getHeading(): string { return ''; }

    protected static ?string $title = 'Station Board';
    protected static ?string $slug = 'fulfillment/station';
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'run')]
    public ?int $runId = null;

    #[Url(as: 'station')]
    public ?int $stationId = null;

    public string $buyerSearch = '';

    /** The buyer list shows a page at a time; null follows the current buyer. */
    public const BUYERS_PER_PAGE = 8;

    public ?int $buyerPage = null;

    public function setBuyerPage(int $page): void
    {
        $pages = max(1, (int) ceil($this->buyers->count() / self::BUYERS_PER_PAGE));
        $this->buyerPage = max(1, min($page, $pages));
    }
    public string $scanCode = '';
    public ?array $flash = null;

    public bool $showFlag = false;
    public string $flagType = 'missing_item';
    public string $flagDetails = '';
    public string $flagOrder = '';
    public ?int $flagPackageId = null;

    public string $exceptionNote = '';
    public string $pauseReason = '';

    public static function canAccess(): bool
    {
        return FulfillmentAccess::moduleEnabled() && FulfillmentAccess::isMember(auth()->user());
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-station-board';
    }

    public function getTitle(): string
    {
        return 'Station Board';
    }

    public function mount(): void
    {
        if ($this->runId) {
            $run = FulfillmentRun::query()->findOrFail($this->runId);
            $this->authorizeView($run);
            $this->stationId ??= $run->station_occupied_id ?? $run->station_id;
        } elseif ($this->stationId) {
            $this->runId = FulfillmentRun::query()->where('station_occupied_id', $this->stationId)->value('id');
        } else {
            // A packer with one show on a station lands straight on it.
            $mine = FulfillmentRun::query()->whereIn('status', FulfillmentRun::ON_STATION)
                ->whereHas('packers', fn ($q) => $q->where('users.id', auth()->id()))->latest('started_at')->first();
            if ($mine) {
                $this->runId = $mine->id;
                $this->stationId = $mine->station_occupied_id;
            }
        }
    }

    private function authorizeView(FulfillmentRun $run): void
    {
        $user = auth()->user();
        abort_unless(FulfillmentAccess::isManager($user) || FulfillmentAccess::canWorkRun($user, $run->id), 403);
    }

    #[Computed]
    public function run(): ?FulfillmentRun
    {
        if (! $this->runId) return null;

        return FulfillmentRun::query()->with([
            'show:id,title,show_date,start_time,whatnot_channel_id,status', 'show.channel:id,name', 'show.streamers:id,name',
            'show.fulfillmentUsers:id,name', 'station:id,name,camera_label', 'packers:id,name', 'acknowledger:id,name', 'approver:id,name',
        ])->find($this->runId);
    }

    #[Computed]
    public function buyers(): \Illuminate\Support\Collection
    {
        if (! $this->run) return collect();

        return FulfillmentRunBuyer::query()->where('run_id', $this->run->id)
            ->withCount(['packages', 'packages as packed_packages_count' => fn ($q) => $q->where('status', 'packed')])
            ->orderBy('id')->get();
    }

    #[Computed]
    public function current(): ?FulfillmentRunBuyer
    {
        $run = $this->run;
        if (! $run) return null;
        $buyer = $run->current_buyer_id ? $this->buyers->firstWhere('id', $run->current_buyer_id) : null;

        return $buyer ?? $this->buyers->first(fn ($b) => $b->status !== 'packed') ?? $this->buyers->first();
    }

    #[Computed]
    public function currentPackages(): \Illuminate\Support\Collection
    {
        return $this->current ? $this->current->packages()->with('shipment:id,whatnot_order_id,tracking_number,carrier,item_count,raw_payload')->get() : collect();
    }

    #[Computed]
    public function coverage(): ?array
    {
        if (! $this->current || ! $this->run || ! $this->run->isOnStation()) return null;

        return app(FulfillmentVerificationService::class)->coverage($this->run->id, $this->current->id);
    }

    #[Computed]
    public function stations(): \Illuminate\Support\Collection
    {
        return FulfillmentStation::query()->where('active', true)->orderBy('sort')->orderBy('id')
            ->with(['activeRun:id,show_id,station_occupied_id,status', 'activeRun.show:id,title'])->get();
    }

    /** Shows this person can start (ready runs), for the station picker. */
    #[Computed]
    public function startable(): \Illuminate\Support\Collection
    {
        return app(FulfillmentQueue::class)->queueRows(auth()->user())->where('status', FulfillmentRun::READY)->values();
    }

    public function isManager(): bool
    {
        return FulfillmentAccess::isManager(auth()->user());
    }

    public function canWork(): bool
    {
        return $this->run && FulfillmentAccess::canWorkRun(auth()->user(), $this->run->id);
    }

    public function chooseStation(int $stationId): void
    {
        $this->stationId = $stationId;
        $occupied = FulfillmentRun::query()->where('station_occupied_id', $stationId)->value('id');
        if ($occupied) $this->runId = $occupied;
    }

    public function openRun(int $runId): void
    {
        $run = FulfillmentRun::query()->findOrFail($runId);
        $this->authorizeView($run);
        $this->runId = $run->id;
        $this->clearComputed();
    }

    public function startPacking(): void
    {
        $run = $this->run;
        abort_unless($run, 404);
        $this->authorizeView($run);
        $this->validate(['stationId' => ['required', 'integer', 'exists:fulfillment_stations,id']], ['stationId.required' => 'Choose the station you are packing at.']);
        $this->act(function () use ($run) {
            app(FulfillmentRunService::class)->start($run->show, (int) $this->stationId, auth()->user());
            $this->say('Packing started at ' . FulfillmentStation::query()->whereKey($this->stationId)->value('name'), true);
        });
    }

    public function selectBuyer(int $buyerId): void
    {
        $this->act(fn () => app(FulfillmentRunService::class)->selectBuyer($this->runId, $buyerId, auth()->user()));
        $this->scanCode = '';
    }

    public function step(int $direction): void
    {
        $list = $this->buyers->values();
        $index = $list->search(fn ($b) => $b->id === $this->current?->id);
        $next = $list->get(max(0, min($list->count() - 1, ($index === false ? 0 : $index) + $direction)));
        if ($next) $this->selectBuyer($next->id);
    }

    /** Pack the current buyer's next open package, or the buyer when it has no package records. */
    public function packNext(): void
    {
        $buyer = $this->current;
        if (! $buyer) return;
        $package = $this->currentPackages->first(fn ($p) => $p->status !== 'packed');
        $this->act(function () use ($buyer, $package) {
            $service = app(FulfillmentRunService::class);
            $package ? $service->packPackage($this->runId, $package->id, auth()->user()) : $service->markPacked($this->runId, $buyer->id, auth()->user());
            $fresh = FulfillmentRunBuyer::query()->find($buyer->id);
            if ($fresh?->status === 'packed') {
                $this->say("{$buyer->letter} · {$buyer->buyer_username} packed", true);
                $next = $this->buyers->first(fn ($b) => $b->id !== $buyer->id && ! in_array($b->status, ['packed', 'held'], true));
                $this->clearComputed();
                if ($next) app(FulfillmentRunService::class)->selectBuyer($this->runId, $next->id, auth()->user());
            } else {
                $this->say('Package packed', true);
            }
        });
    }

    public function packPackage(int $packageId): void
    {
        $this->act(function () use ($packageId) {
            app(FulfillmentRunService::class)->packPackage($this->runId, $packageId, auth()->user());
            $this->say('Package packed', true);
        });
    }

    public function packBuyer(int $buyerId): void
    {
        $this->act(function () use ($buyerId) {
            app(FulfillmentRunService::class)->markPacked($this->runId, $buyerId, auth()->user());
            $this->say('Buyer packed', true);
        });
    }

    public function scan(): void
    {
        $code = trim($this->scanCode);
        $this->scanCode = '';
        if ($code === '' || ! $this->current) return;
        $this->act(function () use ($code) {
            app(FulfillmentVerificationService::class)->verify($this->runId, $this->current->id, $code, auth()->user());
            $this->say('Verified ' . $code, true);
        });
        $this->dispatch('vxf-scan-done');
    }

    public function approveException(): void
    {
        abort_unless($this->isManager() && $this->current, 403);
        $this->act(function () {
            $count = app(FulfillmentVerificationService::class)->approveException($this->runId, $this->current->id, auth()->user(), $this->exceptionNote);
            $this->exceptionNote = '';
            $this->say($count ? "Exception approved for {$count} item(s)" : 'Nothing left to approve', true);
        });
    }

    public function openFlag(?int $packageId = null): void
    {
        $this->showFlag = true;
        $this->flagPackageId = $packageId;
        $this->flagOrder = (string) ($this->current?->order_ids[0] ?? '');
        $this->resetErrorBag();
    }

    public function submitFlag(): void
    {
        $this->validate([
            'flagType' => ['required', 'in:' . implode(',', array_keys(FulfillmentFlagService::CATEGORIES))],
            'flagDetails' => ['required', 'string', 'min:3', 'max:2000'],
            'flagOrder' => ['nullable', 'string', 'max:120'],
        ]);
        $run = $this->run;
        abort_unless($run, 404);
        app(FulfillmentFlagService::class)->report(auth()->user(), $run->show_id, $this->flagType, $this->flagDetails,
            $run->id, $this->current?->id, $this->flagOrder ?: null, $this->flagPackageId);
        $this->reset('showFlag', 'flagDetails', 'flagOrder', 'flagPackageId');
        $this->clearComputed();
        $this->say('Issue reported · buyer on hold until a manager resolves it', false);
    }

    public function pause(): void
    {
        $this->act(function () {
            app(FulfillmentRunService::class)->pause($this->runId, auth()->user(), $this->pauseReason ?: null);
            $this->pauseReason = '';
            $this->say('Packing paused', true);
        });
    }

    public function resume(): void
    {
        $this->act(function () {
            app(FulfillmentRunService::class)->resume($this->runId, auth()->user());
            $this->say('Packing resumed', true);
        });
    }

    public function finish(): void
    {
        $this->act(function () {
            app(FulfillmentRunService::class)->finish($this->runId, auth()->user());
            $this->say('Show packed. Completion record saved for manager approval.', true);
        });
    }

    public function acknowledge(): void
    {
        $this->act(function () {
            app(FulfillmentRunService::class)->acknowledge($this->runId, auth()->user());
            $this->say('Thanks, completion acknowledged', true);
        });
    }

    public function reopenBuyer(int $buyerId): void
    {
        abort_unless($this->isManager(), 403);
        $this->act(fn () => app(FulfillmentRunService::class)->reopenBuyer($this->runId, $buyerId, auth()->user(), 'Reopened from the station board'));
    }

    private function act(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->say(collect($e->errors())->flatten()->first() ?: 'That did not work.', false);
        }
        $this->clearComputed();
        $this->buyerPage = null; // follow the current buyer again
        // Keep the cursor in the scan box so a handheld scanner always types there.
        $this->dispatch('vxf-scan-done');
    }

    private function say(string $message, bool $ok): void
    {
        $this->flash = ['text' => $message, 'ok' => $ok, 'at' => now()->timestamp];
        if (! $ok) Notification::make()->title($message)->warning()->send();
    }

    private function clearComputed(): void
    {
        unset($this->run, $this->buyers, $this->current, $this->currentPackages, $this->coverage, $this->stations, $this->startable);
    }
}
