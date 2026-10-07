<?php

namespace App\Filament\Pages;

use App\Services\NotificationRouter;
use App\Support\NotificationCatalog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Each person's own notification choices: pause everything, in-app/email
 * overall, and per event. Only events that can reach this person are listed.
 * Email can always be turned off; in-app items marked Required cannot.
 */
class MyNotifications extends Page
{
    protected static ?string $title = 'My notifications';
    protected static ?string $slug = 'my-notifications';
    protected static bool $shouldRegisterNavigation = false;

    public bool $enabled = true;
    public bool $inApp = true;
    public bool $email = true;

    /** @var array<string, array{in_app: bool, email: bool}> */
    public array $prefs = [];

    public function getView(): string { return 'filament.pages.my-notifications'; }
    public function getSubheading(): ?string { return 'Choose what reaches you, and whether it comes by email.'; }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->enabled = $user->notifications_enabled !== false;
        $this->inApp = $user->notification_in_app_enabled !== false;
        $this->email = $user->notification_email_enabled !== false;
        foreach (array_keys($this->events()) as $key) $this->prefs[$key] = $user->notificationPreference($key);
    }

    /**
     * Events that can reach this person: they are in the rule's audience, or
     * the event goes to the people involved and this person's role is the kind
     * that gets involved (streamers in their reports, packers in fulfillment…).
     */
    public function events(): array
    {
        $user = auth()->user();
        $router = app(NotificationRouter::class);
        $out = [];

        foreach (NotificationCatalog::events() as $key => $event) {
            $rule = $router->rule($key);
            if (! $rule['enabled'] || (! $rule['in_app'] && ! $rule['email'])) continue;
            $inAudience = $router->recipientsForRule([...$rule, 'involved' => false])->contains('id', $user->id);
            $role = $event['involved_role'] ?? null;
            $couldBeInvolved = $rule['involved'] && ($role === null
                || ($role === 'streamer' && $user->hasRole('streamer'))
                || ($role === 'fulfillment' && $user->hasAnyRole(['fulfillment', 'fulfillment_admin'])));
            if ($inAudience || $couldBeInvolved) $out[$key] = $event + ['rule' => $rule];
        }

        return $out;
    }

    public function appEmailIsOn(): bool
    {
        return NotificationRouter::emailIsEnabled();
    }

    public function setAll(string $channel, bool $on): void
    {
        foreach (array_keys($this->prefs) as $key) $this->prefs[$key][$channel] = $on;
    }

    public function save(): void
    {
        $clean = [];
        foreach ($this->prefs as $key => $p) {
            if (! NotificationCatalog::exists($key)) continue;
            $clean[$key] = ['in_app' => (bool) ($p['in_app'] ?? true), 'email' => (bool) ($p['email'] ?? true)];
        }

        auth()->user()->update([
            'notifications_enabled' => $this->enabled,
            'notification_in_app_enabled' => $this->inApp,
            'notification_email_enabled' => $this->email,
            'notification_preferences' => $clean,
        ]);

        Notification::make()->title('Notification preferences saved')->success()->send();
    }
}
