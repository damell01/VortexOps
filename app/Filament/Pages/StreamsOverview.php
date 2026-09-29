<?php

namespace App\Filament\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class StreamsOverview extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Shows Overview';
    protected static ?string $navigationLabel = 'Shows Overview';
    protected static ?string $slug = 'streams-overview';

    #[Url(as: 'range')]
    public string $datePreset = 'this_month';
    #[Url(as: 'from')]
    public string $dateFrom = '';
    #[Url(as: 'to')]
    public string $dateTo = '';

    public function mount(): void
    {
        if ($this->dateFrom === '' || $this->dateTo === '') $this->applyDatePreset($this->datePreset);
    }

    public function updatedDatePreset(string $value): void
    {
        if ($value !== 'custom') $this->applyDatePreset($value);
    }

    public function applyDatePreset(string $preset): void
    {
        $today = today();
        [$from, $to] = match ($preset) {
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->copy()->subDays(29), $today],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        $this->datePreset = $preset;
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
        unset($this->streamSnapshot, $this->recentShows, $this->attentionShows, $this->recentActivity);
    }

    public function previousPeriod(): void
    {
        [$from, $to] = $this->dateRange();
        $days = $from->diffInDays($to) + 1;
        $this->datePreset = 'custom';
        $this->dateFrom = $from->copy()->subDays($days)->toDateString();
        $this->dateTo = $to->copy()->subDays($days)->toDateString();
    }

    public function nextPeriod(): void
    {
        [$from, $to] = $this->dateRange();
        $days = $from->diffInDays($to) + 1;
        $this->datePreset = 'custom';
        $this->dateFrom = $from->copy()->addDays($days)->toDateString();
        $this->dateTo = $to->copy()->addDays($days)->toDateString();
    }

    public function dateRange(): array
    {
        try { $from = Carbon::parse($this->dateFrom)->startOfDay(); } catch (\Throwable) { $from = today()->startOfMonth(); }
        try { $to = Carbon::parse($this->dateTo)->startOfDay(); } catch (\Throwable) { $to = today()->endOfMonth(); }
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    public function dateRangeLabel(): string
    {
        [$from, $to] = $this->dateRange();
        return $from->isSameYear($to) ? $from->format('M j').' – '.$to->format('M j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-squares-2x2'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('streams'); }
    public static function getNavigationSort(): ?int { return 1; }
    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && ($user?->isAdmin() || $user?->isStreamer());
    }
    public function getSubheading(): ?string { return 'Hosted shows, current Whatnot performance, upcoming schedule, and report workflow in one place.'; }
    public function getView(): string { return 'filament.pages.streams-overview'; }

    protected function scopedShowsQuery()
    {
        $query = Show::query()->inChannelContext()->where('is_operational', true);
        $user = auth()->user();
        if ($user?->isStreamer() && ! $user?->isAdmin() && $user->streamer) $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $user->streamer->id));
        return $query;
    }

    protected function periodQuery()
    {
        [$from, $to] = $this->dateRange();
        return $this->scopedShowsQuery()->whereBetween('show_date', [$from->toDateString(), $to->toDateString()]);
    }

    protected function hostedPast($query)
    {
        return $query->where(function ($hosted) {
            $hosted->where('analytics_sync_status', 'complete')
                ->orWhere('show_duration', '>', 0)
                ->orWhere('gross_revenue', '>', 0)
                ->orWhere('whatnot_net', '>', 0)
                ->orWhereNotNull('completed_earnings')
                ->orWhereHas('streamerLogEntry');
        })->whereNotIn('status', ['cancelled']);
    }

    #[Computed]
    public function streamSnapshot(): array
    {
        $base = $this->hostedPast($this->periodQuery()->whereDate('show_date', '<=', today()));
        return [
            'shows' => (clone $base)->count(),
            'grossRevenue' => (float) (clone $base)->sum('gross_revenue'),
            'netRevenue' => (float) (clone $base)->sum('whatnot_net'),
            'completed' => (clone $base)->where(function ($q) {
                $q->where('analytics_sync_status', 'complete')->orWhereHas('streamerLogEntry');
            })->count(),
        ];
    }

    #[Computed]
    public function upcomingShows(): Collection
    {
        return $this->scopedShowsQuery()->whereDate('show_date','>',today())->whereNotIn('status',['cancelled'])->with('streamers')->orderBy('show_date')->orderBy('start_time')->limit(6)->get();
    }

    #[Computed]
    public function recentShows(): Collection
    {
        return $this->hostedPast($this->periodQuery()->whereDate('show_date','<=',today()))
            ->with(['streamers','streamerLogEntry'])->orderByDesc('show_date')->orderByDesc('start_time')->limit(20)->get();
    }

    #[Computed]
    public function attentionShows(): Collection
    {
        return $this->hostedPast($this->periodQuery()->whereDate('show_date','<=',today()))
            ->whereDoesntHave('streamerLogEntry')->with('streamers')->orderByDesc('show_date')->limit(6)->get();
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        return $this->hostedPast($this->scopedShowsQuery()->whereBetween('show_date',[today()->subDays(2)->toDateString(),today()->toDateString()]))
            ->with(['streamers','streamerLogEntry'])->orderByDesc('show_date')->orderByDesc('start_time')->limit(10)->get();
    }

    public function showUrl(int $id): string { return ShowResource::getUrl('view',['record'=>$id]); }
    public function showsUrl(): string { return Shows::getUrl(['range'=>$this->datePreset,'from'=>$this->dateFrom,'to'=>$this->dateTo]); }
}
