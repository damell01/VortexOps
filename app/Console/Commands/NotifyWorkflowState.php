<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NotifyWorkflowState extends Command
{
    protected $signature = 'workflow:notify-state';
    protected $description = 'Send one-time role notifications when show workflow work becomes actionable.';

    public function handle(): int
    {
        $this->notifyStreamersOfEndedShows();
        $this->notifyFulfillmentAssignments();
        return self::SUCCESS;
    }

    private function notifyStreamersOfEndedShows(): void
    {
        $shows = Show::query()
            ->with(['streamers.user', 'streamerLogEntry'])
            ->whereDate('show_date', '>=', today()->subDays(2))
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->get();

        foreach ($shows as $show) {
            if (! $this->appearsEnded($show)) continue;
            if ($show->streamerLogEntry?->submitted_at) continue;

            foreach ($show->streamers as $streamer) {
                $user = $streamer->user;
                if (! $user) continue;

                $this->notifyOnce(
                    $user,
                    'Show ended — report ready',
                    "{$show->title} is ready for End of Stream. Report the inventory actually sold, given away, used as promo, or otherwise consumed.",
                    'warning'
                );
            }
        }
    }

    private function notifyFulfillmentAssignments(): void
    {
        $shows = Show::query()
            ->with('fulfillmentUsers')
            ->whereDate('show_date', '>=', today()->subDays(7))
            ->whereDate('show_date', '<=', today()->addDays(14))
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->whereHas('fulfillmentUsers')
            ->get();

        foreach ($shows as $show) {
            foreach ($show->fulfillmentUsers as $user) {
                $this->notifyOnce(
                    $user,
                    'Fulfillment show assigned',
                    "You are assigned to fulfillment for {$show->title} ({$show->show_date?->format('M j, Y')}). Open the Fulfillment Center to work its shipment and packing queue.",
                    'info'
                );
            }
        }
    }

    private function appearsEnded(Show $show): bool
    {
        if (! $show->show_date) return false;
        if ($show->show_date->lt(today())) return true;
        if (! $show->show_date->isToday()) return false;

        // Streams can run for many hours. Without a direct "ended" signal,
        // wait eight hours after the scheduled start rather than interrupting
        // an active stream with a premature End of Stream reminder.
        if (! $show->start_time) return now()->hour >= 22;

        $start = Carbon::parse($show->start_time)->format('H:i:s');
        return $start <= now()->subHours(8)->format('H:i:s');
    }

    private function notifyOnce(User $user, string $title, string $body, string $tone): void
    {
        $alreadySent = $user->notifications()
            ->where('created_at', '>=', now()->subDays(45))
            ->where('data->title', $title)
            ->where('data->body', $body)
            ->exists();

        if ($alreadySent) return;

        $notification = Notification::make()->title($title)->body($body);
        $notification = match ($tone) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->sendToDatabase($user);
    }
}
