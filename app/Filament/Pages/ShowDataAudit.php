<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ShowDataAudit extends Page
{
    protected static ?string $title = 'Show Data Audit';
    protected static ?string $navigationLabel = 'Show Data Audit';
    protected static ?string $slug = 'show-data-audit';
    protected static string $view = 'filament.pages.show-data-audit';

    #[Url(as: 'range')]
    public string $datePreset = 'this_month';
    #[Url(as: 'from')]
    public string $dateFrom = '';
    #[Url(as: 'to')]
    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        if ($this->dateFrom === '' || $this->dateTo === '') $this->applyDatePreset($this->datePreset);
    }

    public static function canAccess(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function shouldRegisterNavigation(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-chart-bar-square'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Super Admin'; }
    public static function getNavigationSort(): ?int { return 21; }

    public function updatedDatePreset(string $value): void
    {
        if ($value !== 'custom') $this->applyDatePreset($value);
    }

    public function applyDatePreset(string $preset): void
    {
        $today = today();
        [$from, $to] = match ($preset) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$today->copy()->subDays(29), $today],
            'last_90' => [$today->copy()->subDays(89), $today],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
        $this->datePreset = $preset;
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
    }

    private function range(): array
    {
        try { $from = Carbon::parse($this->dateFrom)->startOfDay(); } catch (\Throwable) { $from = today()->startOfMonth(); }
        try { $to = Carbon::parse($this->dateTo)->endOfDay(); } catch (\Throwable) { $to = today()->endOfMonth(); }
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    public function getAuditData(): array
    {
        [$from, $to] = $this->range();
        $shows = Show::query()->inChannelContext()
            ->whereBetween('show_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with('channel')
            ->orderByDesc('show_date')->orderByDesc('start_time')->get();

        $total = $shows->count();
        $field = fn (string $name) => [
            'present' => $shows->whereNotNull($name)->count(),
            'missing' => $shows->whereNull($name)->count(),
        ];

        $coverage = [
            'gross_revenue' => $field('gross_revenue'),
            'whatnot_net' => $field('whatnot_net'),
            'completed_earnings' => $field('completed_earnings'),
            'show_duration' => $field('show_duration'),
        ];

        $missing = $shows->filter(fn (Show $s) =>
            $s->show_date?->lt(today())
            && ($s->gross_revenue === null || $s->whatnot_net === null || $s->completed_earnings === null || $s->show_duration === null)
        )->take(50)->values();

        return [
            'from' => $from, 'to' => $to, 'total' => $total,
            'gross' => (float) $shows->sum('gross_revenue'),
            'estimatedNet' => (float) $shows->sum('whatnot_net'),
            'completed' => (float) $shows->sum('completed_earnings'),
            'hours' => (float) $shows->sum('show_duration') / 60,
            'coverage' => $coverage, 'missing' => $missing,
            'complete' => $shows->filter(fn (Show $s) => $s->gross_revenue !== null && $s->whatnot_net !== null && $s->show_duration !== null)->count(),
        ];
    }

    public function showUrl(int $id): string { return ShowResource::getUrl('view', ['record' => $id]); }
}
