<?php

namespace App\Filament\Widgets;

use App\Models\Payout;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Illuminate\Database\Eloquent\Model;

class StreamerEarningsChartWidget extends ChartWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 10;
    protected string $color     = 'primary';
    protected ?string $maxHeight = '260px';
    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('payouts');
    }

    public function getHeading(): string
    {
        return 'Earnings — Last 12 Months';
    }

    protected function getFilters(): ?array
    {
        return [
            'months' => 'Last 12 months',
            'weeks'  => 'Last 12 weeks',
        ];
    }

    protected function getData(): array
    {
        $streamerId = $this->record instanceof Streamer ? $this->record->id : null;

        try {
            $query = Payout::query()
                ->when($streamerId, fn ($q) => $q->where('streamer_id', $streamerId))
                ->whereIn('status', ['approved', 'paid']);

            if (($this->filter ?? 'months') === 'weeks') {
                $trend = Trend::query($query)
                    ->between(now()->subWeeks(11)->startOfWeek(), now()->endOfWeek())
                    ->perWeek()
                    ->dateColumn('created_at')
                    ->sum('calculated_payout');

                $labels = $trend->map(fn ($t) => \Carbon\Carbon::parse($t->date)->format('M j'))->toArray();
                $values = $trend->pluck('aggregate')->toArray();
            } else {
                $trend = Trend::query($query)
                    ->between(now()->subMonths(11)->startOfMonth(), now()->endOfMonth())
                    ->perMonth()
                    ->dateColumn('created_at')
                    ->sum('calculated_payout');

                $labels = $trend->map(fn ($t) => \Carbon\Carbon::parse($t->date)->format('M Y'))->toArray();
                $values = $trend->pluck('aggregate')->toArray();
            }
        } catch (\Throwable) {
            $labels = [];
            $values = [];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Earnings ($)',
                    'data'            => $values,
                    'borderColor'     => '#7c3aed',
                    'backgroundColor' => '#7c3aed20',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
