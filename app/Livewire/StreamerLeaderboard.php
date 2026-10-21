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
        return view('livewire.streamer-leaderboard', compact('rows', 'visible', 'pages', 'from', 'to'));
    }
}
