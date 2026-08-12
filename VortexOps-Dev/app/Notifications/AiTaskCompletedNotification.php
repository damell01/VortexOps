<?php

namespace App\Notifications;

use App\Models\AiTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiTaskCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AiTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $duration = $this->task->durationSeconds();
        $label    = match ($this->task->type) {
            'show_ai_mapping' => 'Show AI Mapping',
            default           => ucwords(str_replace('_', ' ', $this->task->type)),
        };

        if ($this->task->status === 'completed') {
            return [
                'title'   => "{$label} complete",
                'body'    => $duration ? "Finished in {$duration}s." : 'Finished.',
                'icon'    => 'heroicon-o-sparkles',
                'color'   => 'success',
                'task_id' => $this->task->id,
                'url'     => $this->resolveUrl(),
            ];
        }

        return [
            'title'   => "{$label} failed",
            'body'    => $this->task->error_message ?? 'An error occurred.',
            'icon'    => 'heroicon-o-exclamation-circle',
            'color'   => 'danger',
            'task_id' => $this->task->id,
            'url'     => $this->resolveUrl(),
        ];
    }

    private function resolveUrl(): ?string
    {
        if ($this->task->type === 'show_ai_mapping' && $this->task->taskable) {
            return route('filament.admin.resources.whatnot-shows.view', ['record' => $this->task->taskable_id]);
        }
        return null;
    }
}
