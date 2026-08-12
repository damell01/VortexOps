<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class ShowsCalendarWidget extends Widget
{
    protected static ?int    $sort        = 10;
    protected static ?string $heading     = null;
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.shows-calendar';

    public int  $year;
    public int  $month;

    public function mount(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function previousMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function nextMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function getShowsProperty(): \Illuminate\Support\Collection
    {
        try {
            $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
            $end   = $start->copy()->endOfMonth();

            return Show::query()
                ->with('whatnotChannel:id,name')
                ->whereBetween('show_date', [$start, $end])
                ->whereNotIn('status', ['cancelled'])
                ->inChannelContext()
                ->get(['id', 'show_date', 'status', 'gross_revenue', 'whatnot_channel_id', 'title'])
                ->groupBy(fn ($s) => $s->show_date->format('Y-m-d'));
        } catch (\Throwable) {
            return collect();
        }
    }

    public function getCalendarDaysProperty(): array
    {
        $start       = Carbon::create($this->year, $this->month, 1);
        $daysInMonth = $start->daysInMonth;
        $firstDow    = $start->dayOfWeek; // 0=Sun

        $days = [];
        // Padding before month start
        for ($i = 0; $i < $firstDow; $i++) {
            $days[] = null;
        }
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $days[] = $d;
        }
        return $days;
    }
}
