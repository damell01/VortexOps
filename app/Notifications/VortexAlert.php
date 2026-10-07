<?php

namespace App\Notifications;

use App\Notifications\Concerns\EmailsWhenEnabled;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A catalogued event with a title, a body and links — for the events that
 * were built as one-off Filament bell messages. Going through the router gives
 * them what the rest have: the admin's recipient rule, people's preferences,
 * and the branded email when email is allowed.
 */
class VortexAlert extends Notification
{
    use EmailsWhenEnabled;

    /**
     * @param  array<string, string>  $links  label => url; the first is the main button
     */
    public function __construct(
        protected string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly string $tone = 'info',
        public readonly array $links = [],
        public readonly ?string $icon = null,
    ) {}

    public function toDatabase(object $notifiable): array
    {
        $n = FilamentNotification::make()->title($this->title)->body($this->body);
        $n = match ($this->tone) {
            'success' => $n->success(),
            'warning' => $n->warning(),
            'danger' => $n->danger(),
            default => $n->info(),
        };
        if ($this->icon) $n->icon($this->icon);

        $i = 0;
        $n->actions(collect($this->links)->map(function ($url, $label) use (&$i) {
            $action = Action::make('link'.$i)->label($label)->url($url)->markAsRead();
            return $i++ === 0 ? $action->button() : $action;
        })->values()->all());

        return $n->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->greeting($this->title);
        if ($this->tone === 'danger') $mail->error();
        foreach (preg_split("/\n+/", trim($this->body)) as $line) $mail->line($line);
        if ($first = array_key_first($this->links)) $mail->action($first, $this->links[$first]);

        return $mail;
    }
}
