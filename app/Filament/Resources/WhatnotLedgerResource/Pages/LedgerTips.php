<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\Business;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Models\Streamer;
use Illuminate\Support\Facades\DB;
use App\Services\LedgerTipReport;
use App\Support\BusinessTime;
use App\Support\ChannelContext;
use App\Support\PaySchedule;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public string $streamer = 'all';

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
            'streamer' => ['required', 'regex:/^(all|[1-9][0-9]*)$/'],
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

    public function streamerTotals(): array
    {
        if ($this->report() === null) return [];
        [$from, $until] = $this->range();
        $rows = app(LedgerTipReport::class)->byStreamer($from->toDateString(), $until->toDateString(), $this->channel);
        if (ctype_digit($this->business)) {
            $rows = array_values(array_filter($rows, fn ($row) => (int) $row['business_id'] === (int) $this->business));
        }
        if ($this->business === 'unassigned') {
            $rows = array_values(array_filter($rows, fn ($row) => $row['business_id'] === null));
        }
        if (ctype_digit($this->streamer)) {
            $rows = array_values(array_filter($rows, fn ($row) => (int) ($row['streamer_id'] ?? 0) === (int) $this->streamer));
        }
        return $rows;
    }

    public function resetFilters(): void
    {
        $this->period = 'week';
        $this->channel = 'all';
        $this->business = 'all';
        $this->streamer = 'all';
        $this->from = null;
        $this->until = null;
        $this->payRun = null;
        $this->resetValidation();
    }

    public function exportCsv(): ?StreamedResponse
    {
        $report = $this->report();
        if ($report === null) {
            return null;
        }

        [$from, $until] = $this->range();

        return response()->streamDownload(function () use ($report) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Week starting', 'Channel', 'Business', 'Tip credits', 'Tip debits / corrections', 'Net ledger tips', 'Transactions', 'Pending / processing', 'Missing amounts']);
            foreach ($report['weeks'] as $row) {
                fputcsv($output, [$row['week'], $row['channel'], $row['business'],
                    number_format($row['credits'] / 100, 2, '.', ''), number_format($row['debits'] / 100, 2, '.', ''),
                    number_format($row['net'] / 100, 2, '.', ''), $row['entries'], $row['pending'], $row['missing_amounts']]);
            }
            fclose($output);
        }, 'ledger-tip-totals-'.$from->toDateString().'-'.$until->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Tip entries without an approved show and streamer allocation. */
    public function unresolvedTipTotals(): array
    {
        if ($this->report() === null) return ['entries' => 0, 'net' => 0.0];
        [$from, $until] = $this->range();
        $row = DB::table('whatnot_ledger_entries as le')
            ->leftJoin('ledger_attributions as a', function ($join) {
                $join->on('a.whatnot_ledger_entry_id', '=', 'le.id')
                    ->where('a.status', '=', 'approved');
            })
            ->whereRaw('LOWER(TRIM(le.transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->whereBetween('le.created_date', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('le.whatnot_channel_id', ChannelContext::currentId()))
            ->when(ctype_digit($this->channel), fn ($q) => $q->where('le.whatnot_channel_id', (int) $this->channel))
            ->when($this->channel === 'unassigned', fn ($q) => $q->whereNull('le.whatnot_channel_id'))
            ->where(fn ($q) => $q->whereNull('a.id')->orWhereNull('a.show_id')->orWhereNull('a.streamer_id'))
            ->selectRaw('COUNT(DISTINCT le.id) as entries, COALESCE(SUM(le.amount), 0) as net')
            ->first();
        return ['entries' => (int) ($row->entries ?? 0), 'net' => round((float) ($row->net ?? 0), 2)];
    }

    /** Export the exact approved show-level tip breakdown currently displayed. */
    public function exportShowTipsCsv(): ?StreamedResponse
    {
        if ($this->report() === null) return null;
        [$from, $until] = $this->range();
        $rows = $this->showTipTotals();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Show ID', 'Show', 'Show date', 'Streamer', 'Tip credits', 'Tip corrections', 'Net tips', 'Transactions']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->show_id, $row->show_title, $row->show_date, $row->streamer_name ?? 'Unassigned',
                    number_format((float) $row->credits, 2, '.', ''),
                    number_format((float) $row->corrections, 2, '.', ''),
                    number_format((float) $row->net, 2, '.', ''), $row->entries]);
            }
            fclose($out);
        }, 'show-tip-breakdown-'.$from->toDateString().'-'.$until->toDateString().'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function showTipTotals(): array
    {
        if ($this->report() === null) return [];
        [$from, $until] = $this->range();
        return DB::table('whatnot_ledger_entries as le')
            ->join('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'le.id')
            ->join('shows as sh', 'sh.id', '=', 'a.show_id')
            ->leftJoin('streamers as st', 'st.id', '=', 'a.streamer_id')
            ->where('a.status', 'approved')
            ->whereRaw('LOWER(TRIM(le.transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->whereBetween('le.created_date', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->whereColumn('sh.whatnot_channel_id', 'le.whatnot_channel_id')
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('le.whatnot_channel_id', ChannelContext::currentId()))
            ->when(ctype_digit($this->channel), fn ($q) => $q->where('le.whatnot_channel_id', (int) $this->channel))
            ->when(ctype_digit($this->streamer), fn ($q) => $q->where('a.streamer_id', (int) $this->streamer))
            ->when(ctype_digit($this->business), fn ($q) => $q->where('st.business_id', (int) $this->business))
            ->when($this->business === 'unassigned', fn ($q) => $q->whereNull('st.business_id'))
            ->groupBy('sh.id', 'sh.title', 'sh.show_date', 'st.id', 'st.name')
            ->selectRaw('sh.id as show_id, sh.title as show_title, sh.show_date, st.name as streamer_name, COUNT(*) as entries, SUM(CASE WHEN le.amount > 0 THEN le.amount ELSE 0 END) as credits, SUM(CASE WHEN le.amount < 0 THEN -le.amount ELSE 0 END) as corrections, SUM(le.amount) as net')
            ->orderByDesc('sh.show_date')->limit(500)->get()->all();
    }

    public function streamers(): array
    {
        return Streamer::query()->orderBy('name')->pluck('name', 'id')->all();
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
