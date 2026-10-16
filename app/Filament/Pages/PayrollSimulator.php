<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\Streamer;
use App\Services\PayrollSimulation;
use App\Support\PaySchedule;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * What-if payroll over the real shows in the system.
 *
 * Pick a period (and a business or person); every figure the pay calculation
 * reads is filled in from the real show, report and pay rates, and any of it
 * can be changed here. Pay is worked out by PayoutService — the same code a
 * pay run uses — on in-memory copies, so nothing is ever written.
 */
class PayrollSimulator extends Page
{
    protected static ?string $title = 'Payroll Simulator';
    protected static ?string $navigationLabel = 'Payroll Simulator';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static string|UnitEnum|null $navigationGroup = 'Payouts';
    protected static ?int $navigationSort = 3;

    public const SHOW_LIMIT = 60;

    public string $from = '';
    public string $to = '';
    public ?int $businessId = null;
    public ?int $streamerId = null;

    /** show id => the inputs as edited (starts as the real values) */
    public array $showInputs = [];
    /** streamer id => the pay rates as edited (starts as the real values) */
    public array $rateInputs = [];
    /** show ids left out of the scenario */
    public array $excluded = [];

    /** show id => the real values, to compare edits against */
    #[Locked] public array $showReal = [];
    /** streamer id => the real rates */
    #[Locked] public array $rateReal = [];
    #[Locked] public array $showIds = [];
    #[Locked] public int $matching = 0;

    public ?int $editingShowId = null;
    public ?int $editingStreamerId = null;

    public function getView(): string { return 'filament.pages.payroll-simulator'; }
    public static function canAccess(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function shouldRegisterNavigation(): bool { return false; }

    public function getSubheading(): ?string
    {
        return 'Real shows, real rates, the real pay calculation — change anything to see what it would pay. Nothing is saved.';
    }

    public function mount(): void
    {
        $this->setPeriod('last');
    }

    public function setPeriod(string $which): void
    {
        [$start, $end] = match ($which) {
            'this' => PaySchedule::periodFor(now(), $this->businessId),
            'four' => [now()->subWeeks(4)->startOfDay(), now()->startOfDay()],
            default => PaySchedule::periodFor(now()->subWeek(), $this->businessId),
        };
        $this->from = $start->toDateString();
        $this->to = $end->toDateString();
        $this->loadShows();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'businessId', 'streamerId'], true)) {
            $this->loadShows();
        }
    }

    /** Fill every input from the real records; keeps edits on shows and people still in view. */
    public function loadShows(): void
    {
        $from = $this->validDate($this->from) ?? now()->subWeek()->toDateString();
        $to = $this->validDate($this->to) ?? now()->toDateString();
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        [$this->from, $this->to] = [$from, $to];

        $sim = app(PayrollSimulation::class);
        $shows = $sim->shows($from, $to, $this->businessId, $this->streamerId, self::SHOW_LIMIT);
        $this->matching = $shows->count() < self::SHOW_LIMIT ? $shows->count() : $sim->countShows($from, $to, $this->businessId, $this->streamerId);

        $showReal = $showInputs = $rateReal = $rateInputs = [];
        foreach ($shows as $show) {
            $showReal[$show->id] = $sim->showInputs($show);
            $showInputs[$show->id] = $this->showInputs[$show->id] ?? $showReal[$show->id];
            foreach ($show->streamers as $streamer) {
                $rateReal[$streamer->id] ??= $sim->rateInputs($streamer);
                $rateInputs[$streamer->id] ??= $this->rateInputs[$streamer->id] ?? $rateReal[$streamer->id];
            }
        }

        $this->showIds = $shows->pluck('id')->all();
        $this->showReal = $showReal;
        $this->showInputs = $showInputs;
        $this->rateReal = $rateReal;
        $this->rateInputs = $rateInputs;
        $this->excluded = array_values(array_intersect($this->excluded, $this->showIds));
    }

    public function toggleShow(int $showId): void
    {
        $this->excluded = in_array($showId, $this->excluded, true)
            ? array_values(array_diff($this->excluded, [$showId]))
            : [...$this->excluded, $showId];
    }

    public function editShow(int $showId): void
    {
        if (! isset($this->showReal[$showId])) {
            return;
        }
        $this->editingShowId = $showId;
        $this->dispatch('open-modal', id: 'sim-show-editor');
    }

    public function editStreamer(int $streamerId): void
    {
        if (! isset($this->rateReal[$streamerId])) {
            return;
        }
        $this->editingStreamerId = $streamerId;
        $this->dispatch('open-modal', id: 'sim-rate-editor');
    }

    public function resetShow(int $showId): void
    {
        if (isset($this->showReal[$showId])) {
            $this->showInputs[$showId] = $this->showReal[$showId];
        }
    }

    public function resetStreamer(int $streamerId): void
    {
        if (isset($this->rateReal[$streamerId])) {
            $this->rateInputs[$streamerId] = $this->rateReal[$streamerId];
        }
    }

    public function resetField(string $kind, int $id, string $field): void
    {
        if ($kind === 'show' && array_key_exists($field, $this->showReal[$id] ?? [])) {
            $this->showInputs[$id][$field] = $this->showReal[$id][$field];
        } elseif ($kind === 'rate' && array_key_exists($field, $this->rateReal[$id] ?? [])) {
            $this->rateInputs[$id][$field] = $this->rateReal[$id][$field];
        }
    }

    public function resetAll(): void
    {
        $this->showInputs = $this->showReal;
        $this->rateInputs = $this->rateReal;
        $this->excluded = [];
    }

    public function showEdits(int $showId): array
    {
        return PayrollSimulation::edits($this->showInputs[$showId] ?? [], $this->showReal[$showId] ?? []);
    }

    public function rateEdits(int $streamerId): array
    {
        return PayrollSimulation::edits($this->rateInputs[$streamerId] ?? [], $this->rateReal[$streamerId] ?? []);
    }

    /** The scenario: the included shows, worked out with the edited inputs and with the real ones. */
    public function simulation(): array
    {
        $sim = app(PayrollSimulation::class);
        $ids = array_values(array_diff($this->showIds, $this->excluded));
        $result = $sim->run($sim->showsById($ids), $this->showInputs, $this->rateInputs);

        // Shows left out still list, greyed, so they can be put back.
        $result['excluded'] = $sim->showsById(array_values(array_intersect($this->showIds, $this->excluded)))
            ->map(fn ($show) => ['id' => $show->id, 'title' => $show->title ?: "Show #{$show->id}", 'date' => $show->show_date,
                'streamers' => $show->streamers->pluck('name')->implode(', ')])->all();

        return $result;
    }

    /** @return array<int, array{value:int,label:string,color:?string}> */
    public function businessOptions(): array
    {
        return Business::query()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Business $b) => ['value' => $b->id, 'label' => $b->name, 'color' => $b->color ?: null])->all();
    }

    /** @return array<int, array{value:int,label:string,color:?string}> */
    public function streamerOptions(): array
    {
        return Streamer::query()->where('status', 'active')
            ->when($this->businessId, fn ($q) => $q->where('business_id', $this->businessId))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (Streamer $s) => ['value' => $s->id, 'label' => $s->name, 'color' => null])->all();
    }

    /** @return array<int, array{value:string,label:string,color:string}> */
    public function payTypeOptions(): array
    {
        $colors = ['#7c3aed', '#8b5cf6', '#a78bfa', '#6d28d9', '#5b21b6', '#c4b5fd', '#9333ea', '#4c1d95', '#a855f7'];

        $options = [];
        foreach (array_values(array_keys(Streamer::assignablePayoutTypeLabels())) as $i => $type) {
            $options[] = ['value' => $type, 'label' => Streamer::assignablePayoutTypeLabels()[$type], 'color' => $colors[$i % count($colors)]];
        }

        return $options;
    }

    /** Which rate fields a pay type reads, for the rate editor. @return array<int,string> */
    public static function rateFieldsFor(?string $type): array
    {
        return match ($type) {
            'profit_share' => ['payout_percentage', 'hourly_rate', 'include_tips'],
            'sales_split' => ['house_cut_percentage', 'sales_split_basis', 'include_tips'],
            'shift_share' => ['hourly_rate', 'pwe_rate', 'label_rate', 'payout_percentage', 'burden_rate_type', 'burden_rate_value', 'include_tips'],
            'hybrid' => ['hourly_rate', 'payout_percentage', 'burden_rate_type', 'burden_rate_value', 'include_tips'],
            'pwe_labels' => ['pwe_rate', 'label_rate', 'hourly_rate', 'include_tips'],
            'hourly' => ['hourly_rate'],
            'package' => ['package_rate', 'include_tips'],
            'flat_rate' => ['package_rate'],
            'custom_formula' => ['custom_payout_formula', 'payout_percentage', 'hourly_rate', 'package_rate', 'pwe_rate', 'label_rate'],
            default => ['payout_percentage', 'hourly_rate', 'include_tips'],
        };
    }

    public static function fieldLabels(): array
    {
        return [
            'gross_revenue' => 'Gross sales', 'whatnot_net' => 'Whatnot net', 'tips' => 'Tips',
            'house_cut_override' => 'House cut override %', 'product_cost' => 'Product cost',
            'hours_streamed' => 'Hours worked', 'number_of_shipments' => 'Shipments',
            'pwe_count' => 'Envelopes (PWE)', 'label_count' => 'Packages (labels)',
            'payout_type' => 'Pay type', 'payout_percentage' => 'Share %', 'house_cut_percentage' => 'House cut %',
            'sales_split_basis' => 'Split basis', 'package_rate' => 'Package / flat rate', 'hourly_rate' => 'Hourly rate',
            'pwe_rate' => 'Per envelope', 'label_rate' => 'Per package', 'include_tips' => 'Include tips',
            'custom_payout_formula' => 'Custom formula', 'burden_rate_type' => 'Burden type', 'burden_rate_value' => 'Burden',
        ];
    }

    private function validDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
