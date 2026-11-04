<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Services\FulfillmentRunService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StationBoard extends Page
{
    protected static string $resource = FulfillmentResource::class;
    protected static bool $shouldRegisterNavigation = false;

    public int $record;
    public ?int $stationId = null;
    public ?int $runId = null;
    public ?int $selectedBuyerId = null;
    public string $scanCode = '';
    public string $flagType = 'missing_item';
    public string $flagDetails = '';

    public function getView(): string
    {
        return 'filament.resources.fulfillment-resource.pages.station-board';
    }

    public function mount(int|string $record): void
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $this->record = (int) $show->id;
        $this->runId = DB::table('fulfillment_runs')
            ->where('show_id', $show->id)->where('status', 'packing')
            ->value('id');
    }

    public function getTitle(): string
    {
        return 'Station Board';
    }

    public function startPacking(): void
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $this->validate(['stationId' => ['required', 'integer', 'exists:fulfillment_stations,id']]);
        try {
            $this->runId = app(FulfillmentRunService::class)->start($show, (int) $this->stationId, auth()->user());
            Notification::make()->title('Packing started')->success()->send();
        } catch (ValidationException $exception) {
            $this->addError('stationId', collect($exception->errors())->flatten()->first() ?? 'Unable to start packing.');
        }
    }

    public function packBuyer(int $buyerId): void
    {
        $this->authorizeActiveRun();
        app(FulfillmentRunService::class)->markPacked($this->runId, $buyerId, auth()->user());
    }

    public function scanBuyerItem(int $buyerId): void
    {
        $this->authorizeActiveRun();
        $this->validate(['scanCode' => ['required', 'string', 'max:128']]);
        try {
            $id = app(\App\Services\FulfillmentVerificationService::class)
                ->verify($this->runId, $buyerId, $this->scanCode, auth()->user());
            $matched = DB::table('fulfillment_verifications')->where('id', $id)->value('result') === 'matched';
            Notification::make()->title($matched ? 'Barcode matched buyer order' : 'Barcode does not match buyer order')
                ->color($matched ? 'success' : 'danger')->send();
            $this->scanCode = '';
        } catch (ValidationException $exception) {
            $this->addError('scanCode', collect($exception->errors())->flatten()->first() ?: 'Scan failed.');
        }
    }

    public function reportIssue(int $buyerId): void
    {
        $this->authorizeActiveRun();
        $this->validate([
            'flagType' => ['required', 'in:missing_item,damaged,wrong_item,wrong_count,cancelled,other'],
            'flagDetails' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        app(\App\Services\FulfillmentFlagService::class)->report(
            auth()->user(), $this->record, $this->flagType, $this->flagDetails, $this->runId, $buyerId,
        );
        $this->reset('flagDetails', 'selectedBuyerId');
        Notification::make()->title('Issue reported — buyer placed on hold')->warning()->send();
    }

    public function finishPacking(): void
    {
        $this->authorizeActiveRun();
        app(FulfillmentRunService::class)->finish($this->runId, auth()->user());
        $this->runId = null;
        Notification::make()->title('Show packing complete')->success()->send();
    }

    private function authorizeActiveRun(): void
    {
        abort_unless($this->runId, 404);
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        abort_unless(DB::table('fulfillment_runs')->where('id', $this->runId)
            ->where('show_id', $show->id)->where('status', 'packing')->exists(), 403);
    }

    public function boardData(): array
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $run = $this->runId ? DB::table('fulfillment_runs')
            ->join('fulfillment_stations', 'fulfillment_stations.id', '=', 'fulfillment_runs.station_id')
            ->where('fulfillment_runs.id', $this->runId)->where('fulfillment_runs.show_id', $show->id)->where('fulfillment_runs.status', 'packing')
            ->select('fulfillment_runs.*', 'fulfillment_stations.name as station_name')->first() : null;
        $buyers = $run ? DB::table('fulfillment_run_buyers')->where('run_id', $run->id)->orderBy('id')->get() : collect();
        $packers = $run ? DB::table('fulfillment_run_packers')
            ->join('users', 'users.id', '=', 'fulfillment_run_packers.user_id')
            ->where('run_id', $run->id)->pluck('users.name')->all() : [];
        $stations = DB::table('fulfillment_stations')->where('active', true)->orderBy('sort')->get();
        return compact('show', 'run', 'buyers', 'packers', 'stations');
    }
}
