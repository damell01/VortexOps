<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Business;
use App\Models\Goal;
use App\Models\WhatnotChannel;
use App\Services\GoalProgress;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/** Monthly goals per business or channel, with progress so far. Admin only. */
class Goals extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'reporting';
    protected static ?string $title = 'Goals';

    public function getHeading(): string { return ''; }
    protected static ?string $slug = 'goals';
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $month = '';

    public string $scope = 'all';
    public string $scopeId = '';
    public string $metric = 'whatnot_net';
    public string $target = '';

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('reporting');
    }

    public function getView(): string { return 'filament.pages.goals'; }

    public function mount(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
    }

    private function monthStart(): Carbon
    {
        try {
            return $this->month !== '' ? Carbon::createFromFormat('Y-m-d', $this->month . '-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->monthStart()->addMonthsNoOverflow($by)->format('Y-m');
    }

    public function updatedScope(): void
    {
        $this->scopeId = '';
    }

    public function save(): void
    {
        $this->target = trim(str_replace(['$', ',', ' '], '', $this->target));
        $this->validate([
            'scope' => 'required|in:' . implode(',', array_keys(Goal::SCOPES)),
            'scopeId' => $this->scope === 'all' ? 'nullable' : 'required|integer',
            'metric' => 'required|in:' . implode(',', array_keys(Goal::METRICS)),
            'target' => 'required|numeric|min:0.01',
        ], [], ['scopeId' => $this->scope === 'channel' ? 'channel' : 'business']);

        Goal::updateOrCreate(
            ['month' => $this->monthStart()->toDateString(), 'scope' => $this->scope, 'scope_id' => $this->scope === 'all' ? null : (int) $this->scopeId, 'metric' => $this->metric],
            ['target' => round((float) $this->target, 2), 'created_by' => auth()->id()],
        );
        $this->target = '';
        Notification::make()->success()->title('Goal saved')->send();
    }

    public function remove(int $id): void
    {
        Goal::query()->whereKey($id)->delete();
        Notification::make()->success()->title('Goal removed')->send();
    }

    public function copyPrevious(): void
    {
        $from = $this->monthStart()->subMonthNoOverflow()->toDateString();
        $to = $this->monthStart()->toDateString();
        $copied = 0;
        foreach (Goal::query()->whereDate('month', $from)->get() as $goal) {
            $new = Goal::firstOrCreate(['month' => $to, 'scope' => $goal->scope, 'scope_id' => $goal->scope_id, 'metric' => $goal->metric],
                ['target' => $goal->target, 'created_by' => auth()->id()]);
            if ($new->wasRecentlyCreated) $copied++;
        }
        Notification::make()->{$copied ? 'success' : 'warning'}()->title($copied ? "Copied {$copied} goals from last month" : 'Nothing new to copy from last month')->send();
    }

    protected function getViewData(): array
    {
        $start = $this->monthStart();

        return [
            'progress' => app(GoalProgress::class)->forMonth($start),
            'monthLabel' => $start->format('F Y'),
            'isCurrent' => $start->isSameMonth(now()),
            'businesses' => Business::query()->orderBy('name')->pluck('name', 'id'),
            'channels' => WhatnotChannel::query()->orderBy('name')->pluck('name', 'id'),
            'hasPrevious' => Goal::query()->whereDate('month', $start->copy()->subMonthNoOverflow()->toDateString())->exists(),
        ];
    }
}
