<?php

namespace App\Livewire;

use App\Filament\Pages\Shows;
use App\Services\StreamerShowLeaderboard;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use App\Support\ChannelContext;
use App\Support\RoleAccess;
use Illuminate\Support\Carbon;
use Livewire\Component;

class StreamerLeaderboard extends Component
{
    public const PERIODS = ['week' => 'This week', 'month' => 'This month'];

    public const PER_PAGE = 6;

    public string $period = 'week';
    public int $page = 1;

    /** The 3D Colosseum is offered on the Show Leaderboard page only, never on dashboards. */
    public bool $arena = false;

    /** Dashboards: a light 3D podium that plays the 3-second battle for a new champion. */
    public bool $arenaCompact = false;

    public static function canView(): bool
    {
        $user = auth()->user();
        // Everyone with the Shows tab: admins, streamers, and roles granted Shows.
        return AdminModules::isEnabled('streams') && (bool) ($user?->isAdmin() || ($user?->isStreamer() && $user->streamer) || ($user && RoleAccess::grants(Shows::class, $user)));
    }

    public function mount(): void { abort_unless(static::canView(), 403); }

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) $this->period = 'week';
        $this->page = 1;
    }

    public function dates(): array
    {
        $now = Carbon::parse(BusinessTime::local()->toDateString());
        return match ($this->period) {
            'month' => [$now->copy()->startOfMonth(), $now],
            default => [$now->copy()->startOfWeek(), $now],
        };
    }

    public function goToPage(int $page): void { $this->page = max(1, $page); }

    public function render()
    {
        abort_unless(static::canView(), 403);
        [$from, $to] = $this->dates();
        $rows = app(StreamerShowLeaderboard::class)->rows(auth()->user(), $from, $to, ChannelContext::currentId());
        $pages = max(1, (int) ceil($rows->count() / self::PER_PAGE));
        $this->page = min(max(1, $this->page), $pages);
        $visible = $rows->slice(($this->page - 1) * self::PER_PAGE, self::PER_PAGE);
        $lineup = ($this->arena || $this->arenaCompact) ? $this->arenaLineup($rows) : null;
        return view('livewire.streamer-leaderboard', compact('rows', 'visible', 'pages', 'from', 'to', 'lineup'));
    }

    /** The top three as the arena needs them: who, how much, and which buddy fights. */
    private function arenaLineup(\Illuminate\Support\Collection $rows): array
    {
        return [
            'period' => $this->period,
            'rows' => $rows->take(3)->values()->map(fn ($row, $i) => [
                'id' => $row['show_id'],
                'rank' => $i + 1,
                'name' => implode(' + ', $row['hosts']),
                'value' => ($row['value'] < 0 ? '-$' : '$').number_format(abs($row['value']), 2),
                'buddy' => $row['buddy'],
                'buddyName' => \Illuminate\Support\Str::headline($row['buddy']),
                'seed' => $row['streamer_id'],
            ])->all(),
        ];
    }
}
