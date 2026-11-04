<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\FulfillmentScheduleService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfillmentSchedule extends Page
{
    protected static ?string $title = 'Fulfillment Schedule';
    protected static ?string $slug = 'fulfillment-schedule';
    protected static bool $shouldRegisterNavigation = false;

    public string $week = '';
    public ?int $employeeId = null;
    public ?int $stationId = null;
    public string $startsAt = '';
    public string $endsAt = '';
    public string $notes = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin() || $user->isFulfillment()));
    }

    public function getView(): string
    {
        return 'filament.pages.fulfillment-schedule';
    }

    public function mount(): void
    {
        $this->week = now()->startOfWeek()->toDateString();
    }

    public function changeWeek(int $weeks): void
    {
        $this->week = CarbonImmutable::parse($this->week)->startOfWeek()->addWeeks($weeks)->toDateString();
    }

    public function createShift(): void
    {
        abort_unless($this->isManager(), 403);
        $data = $this->validate([
            'employeeId' => ['required', 'integer', 'exists:users,id'],
            'stationId' => ['nullable', 'integer', 'exists:fulfillment_stations,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            app(FulfillmentScheduleService::class)->create(
                auth()->user(), (int) $data['employeeId'], $data['startsAt'], $data['endsAt'],
                $data['stationId'] ?? null, $data['notes'] ?? null,
            );
            $this->reset('employeeId', 'stationId', 'startsAt', 'endsAt', 'notes');
            Notification::make()->title('Shift scheduled')->success()->send();
        } catch (ValidationException $e) {
            $this->addError('startsAt', collect($e->errors())->flatten()->first() ?? 'Unable to schedule shift.');
        }
    }

    public function copyPreviousWeek(): void
    {
        abort_unless($this->isManager(), 403);
        $target = CarbonImmutable::parse($this->week)->startOfWeek();
        $count = app(FulfillmentScheduleService::class)->copyWeek(
            auth()->user(), $target->subWeek()->toDateString(), $target->toDateString(),
        );
        Notification::make()->title("Copied {$count} shifts")->success()->send();
    }

    public function isManager(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function scheduleData(): array
    {
        $start = CarbonImmutable::parse($this->week)->startOfWeek();
        $query = DB::table('fulfillment_shifts')
            ->join('users', 'users.id', '=', 'fulfillment_shifts.user_id')
            ->leftJoin('fulfillment_stations', 'fulfillment_stations.id', '=', 'fulfillment_shifts.station_id')
            ->where('starts_at', '>=', $start->toDateTimeString())
            ->where('starts_at', '<', $start->addWeek()->toDateTimeString())
            ->select('fulfillment_shifts.*', 'users.name as employee_name', 'fulfillment_stations.name as station_name')
            ->orderBy('starts_at');
        if (! $this->isManager()) $query->where('user_id', auth()->id());
        $shifts = $query->get();
        $nextShift = ! $this->isManager()
            ? DB::table('fulfillment_shifts as shifts')
                ->leftJoin('fulfillment_stations as stations', 'stations.id', '=', 'shifts.station_id')
                ->where('shifts.user_id', auth()->id())
                ->where('shifts.ends_at', '>=', now()->toDateTimeString())
                ->select('shifts.starts_at', 'shifts.ends_at', 'stations.name as station_name')
                ->orderBy('shifts.starts_at')->first()
            : null;
        $employees = $this->isManager() ? User::role(['fulfillment', 'fulfillment_admin'])->orderBy('name')->get(['id', 'name']) : collect();
        $stations = $this->isManager() ? DB::table('fulfillment_stations')->where('active', true)->orderBy('sort')->get() : collect();
        return compact('start', 'shifts', 'employees', 'stations', 'nextShift');
    }
}
