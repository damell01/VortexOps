<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class NotifyWorkflowState extends Command
{
    protected $signature = 'workflow:notify-state';
    protected $description = 'Send one-time role notifications when assigned workflow work becomes actionable.';

    public function handle(): int
    {
        $this->notifyFulfillmentAssignments();
        return self::SUCCESS;
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
