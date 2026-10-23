<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\Business;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\LedgerTipReport;
use App\Support\BusinessTime;
use App\Support\ChannelContext;
use App\Support\PaySchedule;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class LedgerTips extends Page
{
    protected static string $resource = WhatnotLedgerResource::class;

    protected static ?string $title = 'Ledger tip totals';

    public const PERIODS = ['week' => 'This week', 'last_week' => 'Last week', 'month' => 'This month',
        'last_month' => 'Last month', 'pay_period' => 'Pay period', 'custom' => 'Custom range'];

    #[Url]
    public string $period = 'week';

    #[Url]
    public string $channel = 'all';

    #[Url]
    public string $business = 'all';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public ?int $payRun = null;

    public function getView(): string
    {
        return 'filament.resources.whatnot-ledger-resource.pages.ledger-tips';
    }

    public function mount(): void
    {
        $this->validateFilters();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'until'], true)) {
            $this->period = 'custom';
        }
        $this->resetValidation();
    }

    public function validateFilters(): void
    {
        $this->validate([
            'period' => ['required', 'in:'.implode(',', array_keys(self::PERIODS))],
            'channel' => ['required', 'regex:/^(all|unassigned|[1-9][0-9]*)$/'],
            'business' => ['required', 'regex:/^(all|unassigned|[1-9][0-9]*)$/'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom'],
            'until' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'after_or_equal:from'],
            'payRun' => ['nullable', 'integer', 'exists:weekly_payout_batches,id', 'required_if:period,pay_period'],
        ]);
    }

    public function range(): array
    {
        $today = BusinessTime::local()->startOfDay();

        return match ($this->period) {
            'week' => PaySchedule::standardPeriod($today),
            'last_week' => PaySchedule::standardPeriod($today->copy()->subWeek()),
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'pay_period' => $this->runRange(),
            default => [Carbon::parse($this->from), Carbon::parse($this->until)],
        };
    }

    private function runRange(): array
    {
        $run = WeeklyPayoutBatch::findOrFail($this->payRun);

        return [$run->week_start, $run->week_end];
    }

    public function report(): ?array
    {
        // Validate public Livewire/URL state before it reaches the query.
        try {
            $this->validateFilters();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return null;
        }
        [$from, $until] = $this->range();

        return app(LedgerTipReport::class)->between($from->toDateString(), $until->toDateString(), $this->channel, $this->business);
    }

    public function channels(): array
    {
        return WhatnotChannel::query()
            ->when(ChannelContext::isScoped(), fn ($q) => $q->whereKey(ChannelContext::currentId()))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function businesses(): array
    {
        return Business::ordered()->pluck('name', 'id')->all();
    }

    public function payRuns(): array
    {
        return WeeklyPayoutBatch::query()->orderByDesc('week_start')->get()->mapWithKeys(fn ($run) => [
            $run->id => '#'.$run->id.' · '.$run->week_start->format('M j').'–'.$run->week_end->format('M j, Y').' · '.$run->businessLabel(),
        ])->all();
    }
}
