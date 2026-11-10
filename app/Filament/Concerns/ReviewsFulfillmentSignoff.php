<?php

namespace App\Filament\Concerns;

use App\Models\FulfillmentEvent;
use App\Models\FulfillmentFlag;
use App\Models\FulfillmentRun;
use App\Models\FulfillmentRunBuyer;
use App\Models\FulfillmentRunPackage;
use App\Services\FulfillmentRunService;
use App\Support\FulfillmentAccess;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * The fulfillment admin's final sign-off, usable from any page: a queue of
 * finished shows waiting for it, and the full-screen review that opens over
 * the page (resources/views/fulfillment/signoff-review.blade.php) with one
 * "Sign off show" confirm.
 */
trait ReviewsFulfillmentSignoff
{
    /** The show open in the full-screen sign-off review (?signoff=ID). */
    #[Url(as: 'signoff')]
    public ?int $signoffId = null;

    public int $signoffPage = 1;

    public string $signoffNote = '';

    public static int $signoffBuyersPerPage = 8;

    protected function canSignOff(): bool
    {
        return FulfillmentAccess::isManager(auth()->user());
    }

    /** Finished shows waiting for the fulfillment admin, oldest first. */
    #[Computed]
    public function signoffQueue(): Collection
    {
        if (! $this->canSignOff()) return collect();

        return FulfillmentRun::query()->with(['show:id,title,show_date', 'acknowledger:id,name', 'packers:id,name', 'station:id,name'])
            ->withCount(['flags as open_flags_count' => fn ($q) => $q->where('status', 'open')])
            ->where('status', FulfillmentRun::COMPLETED)->orderBy('finished_at')->limit(50)->get();
    }

    #[Computed]
    public function signoffRun(): ?FulfillmentRun
    {
        if (! $this->signoffId || ! $this->canSignOff()) return null;

        return FulfillmentRun::query()->with(['show:id,title,show_date,start_time,whatnot_channel_id,whatnot_show_id', 'show.channel:id,name', 'show.streamers:id,name',
            'station:id,name', 'packers:id,name', 'acknowledger:id,name', 'approver:id,name'])
            ->whereIn('status', [FulfillmentRun::COMPLETED, FulfillmentRun::APPROVED])->find($this->signoffId);
    }

    #[Computed]
    public function signoffData(): array
    {
        return $this->signoffRun ? $this->recordData($this->signoffRun) : [];
    }

    public function openSignoff(int $runId): void
    {
        abort_unless($this->canSignOff(), 403);
        $this->signoffId = $runId;
        $this->signoffPage = 1;
        $this->signoffNote = '';
        unset($this->signoffRun, $this->signoffData);
        if (method_exists($this, 'signoffOpened')) $this->signoffOpened();
    }

    public function closeSignoff(): void
    {
        $this->signoffId = null;
        unset($this->signoffRun, $this->signoffData, $this->signoffQueue);
    }

    public function setSignoffPage(int $page): void
    {
        $this->signoffPage = max(1, $page);
    }

    /** One confirm: the fulfillment admin has reviewed the show and signs it off. */
    public function signOff(): void
    {
        abort_unless($this->canSignOff() && $this->signoffId, 403);
        try {
            app(FulfillmentRunService::class)->signOff($this->signoffId, auth()->user(), $this->signoffNote ?: null);
            $this->signoffNote = '';
            Notification::make()->title('Show signed off')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->warning()->send();
        }
        unset($this->signoffRun, $this->signoffData, $this->signoffQueue);
        if (method_exists($this, 'signoffSaved')) $this->signoffSaved();
    }

    /** Buyers, packages, events, issues and who packed what for one show. */
    protected function recordData(FulfillmentRun $run): array
    {
        $buyers = FulfillmentRunBuyer::query()->where('run_id', $run->id)->with('packer:id,name')
            ->withCount(['packages', 'packages as packed_packages_count' => fn ($q) => $q->where('status', 'packed')])->orderBy('id')->get();
        $packages = FulfillmentRunPackage::query()->where('run_id', $run->id)->with(['buyer:id,letter,buyer_username', 'packer:id,name', 'shipment:id,whatnot_order_id,tracking_number,carrier'])->orderBy('id')->get();
        $events = FulfillmentEvent::query()->where('run_id', $run->id)->with('user:id,name')->orderBy('id')->limit(1000)->get();
        $flags = FulfillmentFlag::query()->where('run_id', $run->id)->with(['buyer:id,letter,buyer_username', 'reporter:id,name', 'resolver:id,name'])->latest()->get();
        $contributions = $packages->where('status', 'packed')->groupBy('packed_by')->map(fn ($rows) => ['name' => $rows->first()->packer?->name ?? 'Unknown', 'packages' => $rows->count()])->values();
        if ($contributions->isEmpty()) {
            $contributions = $buyers->where('status', 'packed')->groupBy('packed_by')->map(fn ($rows) => ['name' => $rows->first()->packer?->name ?? 'Unknown', 'buyers' => $rows->count()])->values();
        }
        $verified = (int) DB::table('fulfillment_verifications')->where('run_id', $run->id)->whereIn('result', ['matched', 'override'])->sum('quantity');
        $rejected = DB::table('fulfillment_verifications')->where('run_id', $run->id)->where('result', 'unmatched')->count();

        return compact('buyers', 'packages', 'events', 'flags', 'contributions', 'verified', 'rejected');
    }
}
