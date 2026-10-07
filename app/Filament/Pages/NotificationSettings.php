<?php

namespace App\Filament\Pages;

use App\Listeners\LogOutgoingEmail;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationRouter;
use App\Support\NotificationCatalog;
use App\Support\NotificationSamples;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;

/**
 * Settings → Notifications: who gets each notification, how, and the owner's
 * global email controls. Every email can be previewed or sent as a test.
 */
class NotificationSettings extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Notifications';
    protected static ?string $slug = 'notification-settings';

    /** @var array<string, array> event => rule, as edited on the page */
    public array $rules = [];

    public bool $emailEnabled = false;
    public int $hourlyCap = NotificationRouter::DEFAULT_HOURLY_CAP;
    public string $testAddress = '';
    public string $search = '';

    public ?string $previewEvent = null;
    public string $previewHtml = '';

    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-bell-alert'; }
    public function getView(): string { return 'filament.pages.notification-settings'; }
    public function getSubheading(): ?string { return 'Choose what deserves a notification, who gets it, and whether it goes by email.'; }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function mount(): void
    {
        $router = app(NotificationRouter::class);
        $existing = User::pluck('id')->all();
        foreach (NotificationCatalog::events() as $key => $event) {
            $rule = $router->rule($key);
            // A person deleted since they were picked simply drops off the list.
            $rule['users'] = array_map('strval', array_values(array_intersect($rule['users'], $existing)));
            $rule['emails'] = implode(', ', $rule['emails']);
            $this->rules[$key] = $rule;
        }

        $this->emailEnabled = filter_var(Setting::get('notify_email_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $this->hourlyCap = NotificationRouter::hourlyCap();
        $this->testAddress = (string) (auth()->user()?->email ?? '');
    }

    public function isOwner(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    /** The server's own safety switch (NOTIFICATION_EMAILS_ENABLED) — set in the environment, not here. */
    public function serverAllowsEmail(): bool
    {
        return filter_var(config('mail.notification_emails_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function mailer(): string
    {
        return (string) config('mail.default');
    }

    /** Fixed audiences plus every other role, as checkbox options. */
    #[Computed]
    public function audiences(): array
    {
        $fixed = NotificationCatalog::audiences();
        $covered = ['admin', 'super_admin', 'fulfillment', 'fulfillment_admin', 'streamer'];
        foreach (Role::where('guard_name', 'web')->whereNotIn('name', $covered)->orderBy('name')->pluck('name') as $name) {
            $fixed['role:'.$name] = ucwords(str_replace(['_', '-'], ' ', $name));
        }
        return $fixed;
    }

    #[Computed]
    public function people(): Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'email']);
    }

    /** How many people each event currently reaches (not counting "involved"). */
    public function reach(string $key): int
    {
        return app(NotificationRouter::class)->recipientsForRule($this->ruleFromForm($key))->count();
    }

    public function toggleAudience(string $key, string $audience): void
    {
        $roles = $this->rules[$key]['roles'] ?? [];
        $this->rules[$key]['roles'] = in_array($audience, $roles, true)
            ? array_values(array_diff($roles, [$audience]))
            : [...$roles, $audience];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['hourlyCap' => 'integer|min:0|max:500']);

        $out = [];
        foreach (array_keys(NotificationCatalog::events()) as $key) $out[$key] = $this->ruleFromForm($key);

        foreach ($out as $key => $rule) {
            if (count($rule['emails']) !== count($this->splitEmails($this->rules[$key]['emails'] ?? ''))) {
                $this->addError("rules.{$key}.emails", 'One of these is not an email address.');
                return;
            }
        }

        app(NotificationRouter::class)->saveRules($out);

        // Global email controls belong to the owner.
        if ($this->isOwner()) {
            Setting::set('notify_email_enabled', $this->emailEnabled ? '1' : '0');
            Setting::set('notify_email_hourly_cap', (string) $this->hourlyCap);
        }

        Notification::make()->title('Notification settings saved')->success()->send();
    }

    private function ruleFromForm(string $key): array
    {
        $r = $this->rules[$key] ?? [];
        return [
            'enabled' => (bool) ($r['enabled'] ?? true),
            'roles' => array_values(array_intersect((array) ($r['roles'] ?? []), array_keys($this->audiences))),
            'users' => array_values(array_intersect(array_unique(array_map('intval', array_filter((array) ($r['users'] ?? [])))), $this->people->pluck('id')->all())),
            'involved' => (bool) ($r['involved'] ?? false),
            'emails' => array_values(array_filter($this->splitEmails($r['emails'] ?? ''), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))),
            'in_app' => (bool) ($r['in_app'] ?? true),
            'email' => (bool) ($r['email'] ?? false),
        ];
    }

    private function splitEmails(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $value) ?: [])));
    }

    // ── Preview & test ──────────────────────────────────────────────────

    public function preview(string $key): void
    {
        abort_unless(NotificationCatalog::exists($key), 404);
        $this->previewEvent = $key;
        $this->previewHtml = $this->renderEmail($key);
    }

    public function closePreview(): void
    {
        $this->previewEvent = null;
        $this->previewHtml = '';
    }

    public function renderEmail(string $key): string
    {
        try {
            $notification = NotificationSamples::make($key);
            return method_exists($notification, 'toMail')
                ? (string) $notification->toMail(auth()->user())->render()
                : '<p style="font-family:sans-serif;padding:24px">This notification has no email version.</p>';
        } catch (\Throwable $e) {
            report($e);
            return '<p style="font-family:sans-serif;padding:24px;color:#b91c1c">Could not build this email: '.e($e->getMessage()).'</p>';
        }
    }

    /** Send one sample email, ignoring rules and preferences; marked as a test in the log. */
    public function sendTest(string $key): void
    {
        abort_unless(NotificationCatalog::exists($key), 404);
        $this->sendTests([$key]);
    }

    public function sendAllTests(): void
    {
        $this->sendTests(array_keys(NotificationCatalog::events()));
    }

    private function sendTests(array $keys): void
    {
        abort_unless(static::canAccess(), 403);
        $to = trim($this->testAddress);
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->addError('testAddress', 'Enter the address to send tests to.');
            return;
        }

        $sent = 0;
        $failed = [];
        LogOutgoingEmail::$testing = true;
        try {
            foreach ($keys as $key) {
                try {
                    // AnonymousNotifiable::notifyNow() ignores a channel list; sendNow() honours it.
                    NotificationFacade::sendNow(NotificationFacade::route('mail', $to), NotificationSamples::make($key), ['mail']);
                    $sent++;
                } catch (\Throwable $e) {
                    LogOutgoingEmail::failLast($e->getMessage());
                    $failed[] = NotificationCatalog::label($key).': '.$e->getMessage();
                }
            }
        } finally {
            LogOutgoingEmail::$testing = false;
        }

        $mailerNote = in_array($this->mailer(), ['log', 'array'], true) ? " The mailer is \"{$this->mailer()}\", so nothing leaves the server — see the Email Log." : '';

        $failed === []
            ? Notification::make()->title($sent === 1 ? 'Test email sent' : "{$sent} test emails sent")->body("To {$to}.{$mailerNote}")->success()->send()
            : Notification::make()->title(count($failed).' test email(s) failed')->body(implode("\n", array_slice($failed, 0, 3)))->danger()->persistent()->send();
    }
}
