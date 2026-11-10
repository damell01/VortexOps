<?php

namespace App\Livewire;

use App\Models\Streamer;
use App\Services\LedgerChargeReport;
use App\Support\PaySchedule;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ledger charges (chargebacks) report, shared by the admin page (everyone,
 * with a streamer filter) and a streamer's own page (locked to them).
 */
class LedgerCharges extends Component
{
    /** Set by the streamer's own page: they only ever see their lines. */
    #[Locked]
    public ?int $lockedStreamerId = null;

    #[Url(as: 'period')]
    public string $period = 'pay';

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'until')]
    public ?string $until = null;

    #[Url(as: 'streamer')]
    public ?int $streamer = null;

    #[Url(as: 'type')]
    public ?string $category = null;

    public int $page = 1;

    public const PER_PAGE = 20;

    public const PERIODS = ['pay' => 'This pay period', 'last_pay' => 'Last pay period', 'month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom'];

    public function mount(?int $lockedStreamerId = null): void
    {
        $this->lockedStreamerId = $lockedStreamerId;
        if (! array_key_exists($this->period, self::PERIODS)) $this->period = 'pay';
        if ($this->category !== null && ! in_array($this->category, LedgerChargeReport::CATEGORIES, true)) $this->category = null;
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['period', 'from', 'until', 'streamer', 'category'], true)) {
            $this->page = 1;
            unset($this->range, $this->summary, $this->lines, $this->total);
        }
    }

    public function setPage(int $page): void
    {
        $this->page = max(1, min($page, $this->pageCount()));
        unset($this->lines);
    }

    /** The streamer the report is limited to: the locked one, or the admin's pick. */
    private function streamerId(): ?int
    {
        return $this->lockedStreamerId ?: ($this->streamer ?: null);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    #[Computed]
    public function range(): array
    {
        $business = LedgerChargeReport::chargedBusinessId();
        $today = now(\App\Support\BusinessTime::zone())->startOfDay();

        return match ($this->period) {
            'last_pay' => PaySchedule::periodFor(PaySchedule::periodFor($today, $business)[0]->copy()->subDay(), $business),
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'custom' => [Carbon::parse($this->validDate($this->from) ?? $today->copy()->startOfMonth())->startOfDay(),
                Carbon::parse($this->validDate($this->until) ?? $today)->startOfDay()],
            default => PaySchedule::periodFor($today, $business),
        };
    }

    private function validDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    #[Computed]
    public function summary(): array
    {
        [$from, $until] = $this->range;

        return app(LedgerChargeReport::class)->summary($from->toDateString(), $until->toDateString(), $this->streamerId());
    }

    #[Computed]
    public function total(): int
    {
        [$from, $until] = $this->range;

        return app(LedgerChargeReport::class)->count($from->toDateString(), $until->toDateString(), $this->streamerId(), $this->category);
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / self::PER_PAGE));
    }

    #[Computed]
    public function lines(): \Illuminate\Support\Collection
    {
        [$from, $until] = $this->range;
        $this->page = max(1, min($this->page, $this->pageCount()));

        return app(LedgerChargeReport::class)->lines($from->toDateString(), $until->toDateString(), $this->streamerId(), $this->category, $this->page, self::PER_PAGE);
    }

    /** Estill streamers, for the admin's streamer filter. */
    #[Computed]
    public function streamerOptions(): array
    {
        if ($this->lockedStreamerId) return [];

        return Streamer::query()->where('business_id', LedgerChargeReport::chargedBusinessId() ?? -1)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function export(): StreamedResponse
    {
        [$from, $until] = $this->range;
        $streamerId = $this->streamerId();
        $category = $this->category;
        $name = 'ledger-charges_' . $from->format('Ymd') . '-' . $until->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($from, $until, $streamerId, $category) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Streamer', 'Type', 'Ledger transaction', 'Show', 'Channel', 'Order', 'Amount', 'Note']);
            app(LedgerChargeReport::class)->query($from->toDateString(), $until->toDateString(), $streamerId, $category)
                ->orderByDesc('le.created_date')
                ->select(['le.created_date', 's.name as streamer', 'a.category', 'le.transaction_type', 'sh.title as show_title', 'c.name as channel', 'le.whatnot_order_id', 'le.amount', 'le.message'])
                ->lazy(500)
                ->each(fn ($r) => fputcsv($out, [
                    Carbon::parse($r->created_date)->timezone(\App\Support\BusinessTime::zone())->format('Y-m-d H:i'), $r->streamer,
                    LedgerChargeReport::LABELS[$r->category] ?? $r->category, $r->transaction_type, $r->show_title, $r->channel,
                    $r->whatnot_order_id, number_format((float) $r->amount, 2, '.', ''), $r->message,
                ]));
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.ledger-charges');
    }
}
