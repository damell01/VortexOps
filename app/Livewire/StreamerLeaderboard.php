<?php

namespace App\Livewire;

use App\Services\StreamerShowLeaderboard;
use App\Support\AdminModules;
use App\Support\BusinessTime;
use App\Support\ChannelContext;
use Illuminate\Support\Carbon;
use Livewire\Component;

class StreamerLeaderboard extends Component
{
    public const PERIODS = ['week' => 'This week', 'month' => 'This month'];

    public string $period = 'week';
    public int $shown = 5;

    public static function canView(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && (bool) ($user?->isAdmin() || ($user?->isStreamer() && $user->streamer));
    }

    public function mount(): void { abort_unless(static::canView(), 403); }

    public function updated(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) $this->period = 'week';
        $this->shown = 5;
    }

    public function dates(): array
    {
        $now = Carbon::parse(BusinessTime::local()->toDateString());
        return match ($this->period) {
            'month' => [$now->copy()->startOfMonth(), $now],
            default => [$now->copy()->startOfWeek(), $now],
        };
    }

    public function showMore(): void { $this->shown = min(max(5, $this->shown) + 10, 100); }

    public function render()
    {
        abort_unless(static::canView(), 403);
        [$from, $to] = $this->dates();
        $rows = app(StreamerShowLeaderboard::class)->rows(auth()->user(), $from, $to, ChannelContext::currentId());
        return view('livewire.streamer-leaderboard', compact('rows', 'from', 'to'));
    }
}
