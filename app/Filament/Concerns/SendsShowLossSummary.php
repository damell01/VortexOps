<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Notifications\ShowLossSummaryNotification;
use App\Services\NotificationRouter;
use App\Support\NotificationSchedule;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The "Send summary" side panel on a report: send the shows on screen now,
 * to the notification's usual recipients, just yourself, or people you
 * pick, by email and/or in-app. Schedules stay in Settings → Notifications.
 *
 * The page provides lossDigest(): the ShowLossDigest array for what it shows.
 */
trait SendsShowLossSummary
{
    public bool $sendOpen = false;

    public string $sendTo = 'rule';

    /** @var list<string> */
    public array $sendUsers = [];

    public bool $sendEmail = true;

    public bool $sendInApp = true;

    public string $sendPreview = '';

    abstract public function lossEvent(): string;

    abstract public function lossDigest(): array;

    public function openSend(): void
    {
        $rule = app(NotificationRouter::class)->rule($this->lossEvent());
        $this->sendEmail = $rule['email'] && NotificationRouter::emailIsEnabled();
        $this->sendInApp = true;
        $this->sendPreview = '';
        $this->sendOpen = true;
    }

    public function closeSend(): void
    {
        $this->sendOpen = false;
        $this->sendPreview = '';
    }

    /** Admins and the owner, who can be picked as recipients. */
    public function sendPeople(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email'])->filter(fn (User $u) => $u->isOwner() || $u->isAdmin())->values();
    }

    public function sendRuleSummary(): string
    {
        $rule = app(NotificationRouter::class)->rule($this->lossEvent());
        $n = app(NotificationRouter::class)->recipientsForRule(['enabled' => true] + $rule)->count();

        return "Everyone on this notification ({$n} ".str('person')->plural($n).')';
    }

    public function sendSchedule(): string
    {
        $s = app(NotificationRouter::class)->rule($this->lossEvent())['schedule'] ?? null;

        return $s ? NotificationSchedule::describe($s) : '';
    }

    /** @return Collection<int, User> */
    private function sendRecipients(): Collection
    {
        return match ($this->sendTo) {
            'me' => collect([auth()->user()])->filter(),
            'pick' => $this->sendPeople()->whereIn('id', array_map('intval', $this->sendUsers))->values(),
            // Sending by hand works even while the scheduled one is switched off.
            default => app(NotificationRouter::class)->recipientsForRule(['enabled' => true] + app(NotificationRouter::class)->rule($this->lossEvent())),
        };
    }

    public function previewSend(): void
    {
        try {
            $this->sendPreview = (string) (new ShowLossSummaryNotification($this->lossEvent(), $this->lossDigest()))->toMail(auth()->user())->render();
        } catch (\Throwable $e) {
            report($e);
            $this->sendPreview = '<p style="font-family:sans-serif;padding:24px;color:#b91c1c">Could not build the preview: '.e($e->getMessage()).'</p>';
        }
    }

    public function sendNow(): void
    {
        $channels = array_values(array_filter([$this->sendInApp ? 'database' : null, $this->sendEmail ? 'mail' : null]));
        if ($channels === []) {
            $this->addError('sendChannels', 'Pick email, in-app or both.');

            return;
        }
        $people = $this->sendRecipients();
        if ($people->isEmpty()) {
            $this->addError('sendUsers', 'Pick at least one person.');

            return;
        }

        $notification = new ShowLossSummaryNotification($this->lossEvent(), $this->lossDigest(), $channels);
        $sent = 0;
        foreach ($people as $user) {
            try {
                $user->notify($notification);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $emailOff = $this->sendEmail && ! NotificationRouter::emailIsEnabled();

        Notification::make()->title('Summary sent to '.$sent.' '.str('person')->plural($sent))
            ->body($emailOff ? 'Email delivery is turned off in Settings → Notifications, so it went in-app only.' : null)
            ->success()->send();
        $this->closeSend();
    }
}
