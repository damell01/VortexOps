<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\NotificationCatalog;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Who gets each notification, and on which channels.
 *
 * Three layers decide a delivery:
 *   1. The owner's global switches — email on/off for the whole app, and an
 *      hourly limit per person so nobody is buried.
 *   2. The event's rule, set by an admin: who receives it and whether it may
 *      go by email and/or in-app.
 *   3. The person's own choices: pause everything, in-app/email overall, and
 *      per event. Locked events stay in-app whatever they choose.
 *
 * Rules live in the `notification_rules` setting as JSON keyed by event. An
 * event with no saved rule falls back to the older notify_<type>_mode/_users
 * settings, then to the catalog default — so nothing changes on deploy until
 * someone edits Settings → Notifications.
 */
class NotificationRouter
{
    public const DEFAULT_HOURLY_CAP = 12;

    private ?array $rules = null;

    /**
     * Everyone the event's audience names, without the people "involved" —
     * kept for existing callers that pass no involved users.
     */
    public function getRecipients(string $type): Collection
    {
        return $this->recipientsFor($type);
    }

    /**
     * Resolve the people an event goes to.
     *
     * @param  iterable<User|null>  $involved  the people the event is about;
     *                                         included only when the rule says so
     */
    public function recipientsFor(string $type, iterable $involved = []): Collection
    {
        return $this->recipientsForRule($this->rule($type), $involved);
    }

    /** Resolve a rule (saved or still being edited) to people. */
    public function recipientsForRule(array $rule, iterable $involved = []): Collection
    {
        if (! $rule['enabled']) return new Collection();

        $users = new Collection();
        foreach ($rule['roles'] as $audience) $users = $users->merge($this->audience($audience));
        if ($rule['users'] !== []) $users = $users->merge(User::whereIn('id', $rule['users'])->get());
        if ($rule['involved']) {
            foreach ($involved as $u) if ($u instanceof User) $users->push($u);
        }

        return $users->unique('id')->values();
    }

    /** The effective rule for an event: saved JSON → legacy settings → catalog default. */
    public function rule(string $type): array
    {
        $this->rules ??= json_decode((string) Setting::get('notification_rules', '{}'), true) ?: [];
        $event = NotificationCatalog::get($type) ?? ['roles' => ['admins'], 'involved' => false, 'in_app' => true, 'email' => false];

        $default = [
            'enabled' => true,
            'roles' => $event['roles'],
            'users' => [],
            'involved' => (bool) $event['involved'],
            'emails' => [],
            'in_app' => (bool) $event['in_app'],
            'email' => (bool) $event['email'],
        ];

        if (! isset($this->rules[$type])) {
            // Settings saved before this page existed.
            $mode = Setting::get("notify_{$type}_mode");
            if ($mode === 'all') $default['roles'] = ['everyone'];
            elseif ($mode === 'admins') $default['roles'] = ['admins'];
            elseif ($mode === 'custom') {
                $default['roles'] = [];
                $default['users'] = array_map('intval', json_decode((string) Setting::get("notify_{$type}_users", '[]'), true) ?: []);
            }
            if ($type === 'show_ready' && filled($extra = Setting::get('show_ready_notification_email'))) $default['emails'] = [$extra];

            return $default;
        }

        $saved = $this->rules[$type];

        return [
            'enabled' => (bool) ($saved['enabled'] ?? true),
            'roles' => array_values(array_filter((array) ($saved['roles'] ?? []), 'is_string')),
            'users' => array_values(array_map('intval', (array) ($saved['users'] ?? []))),
            'involved' => (bool) ($saved['involved'] ?? $default['involved']),
            'emails' => array_values(array_filter((array) ($saved['emails'] ?? []), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))),
            'in_app' => (bool) ($saved['in_app'] ?? $default['in_app']),
            'email' => (bool) ($saved['email'] ?? $default['email']),
        ];
    }

    /** Persist rules for the given events (others keep whatever they had). */
    public function saveRules(array $rules): void
    {
        $all = json_decode((string) Setting::get('notification_rules', '{}'), true) ?: [];
        foreach ($rules as $type => $rule) if (NotificationCatalog::exists($type)) $all[$type] = $rule;
        Setting::set('notification_rules', json_encode($all));
        $this->rules = null;
    }

    /**
     * Channels this event uses for this person right now.
     *
     * @return list<string> some of 'database', 'mail'
     */
    public function channelsFor(string $type, object $notifiable): array
    {
        $rule = $this->rule($type);
        $event = NotificationCatalog::get($type) ?? [];
        $channels = [];

        $isUser = $notifiable instanceof User;
        $prefs = $isUser ? $notifiable->notificationPreference($type) : ['in_app' => true, 'email' => true];
        $master = ! $isUser || $notifiable->notifications_enabled !== false;

        if ($rule['in_app'] && $isUser && (($event['locked'] ?? false) || ($master && $notifiable->notification_in_app_enabled !== false && $prefs['in_app']))) {
            $channels[] = 'database';
        }

        if ($rule['email'] && static::emailIsEnabled() && $master
            && (! $isUser || ($notifiable->notification_email_enabled !== false && $prefs['email']))
            && ! $this->overHourlyCap($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** The owner's master switch, behind the server's own safety switch. */
    public static function emailIsEnabled(): bool
    {
        if (! filter_var(config('mail.notification_emails_enabled', false), FILTER_VALIDATE_BOOLEAN)) return false;

        // Cast rather than trust: the settings table stores '1'/'0' strings, and '0' is truthy.
        return filter_var(Setting::get('notify_email_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function hourlyCap(): int
    {
        return max(0, (int) Setting::get('notify_email_hourly_cap', self::DEFAULT_HOURLY_CAP));
    }

    /** True once this address has had its share of emails in the last hour (0 = no limit). */
    private function overHourlyCap(object $notifiable): bool
    {
        $cap = static::hourlyCap();
        if ($cap === 0) return false;
        $email = $notifiable instanceof User ? $notifiable->email : $notifiable->routeNotificationFor('mail');
        if (! is_string($email) || $email === '') return false;

        try {
            return EmailLog::where('to_email', $email)->where('is_test', false)
                ->whereIn('status', ['sent', 'sending'])
                ->where('created_at', '>=', now()->subHour())->count() >= $cap;
        } catch (\Throwable) {
            return false;
        }
    }

    private function audience(string $key): Collection
    {
        return match (true) {
            $key === 'everyone' => User::all(),
            $key === 'owner' => User::where('email', config('app.owner_email'))->get(),
            $key === 'admins' => $this->withRoles(['admin', 'super_admin'])->merge(User::where('email', config('app.owner_email'))->get()),
            $key === 'super_admins' => $this->withRoles(['super_admin']),
            $key === 'fulfillment' => $this->withRoles(['fulfillment', 'fulfillment_admin']),
            $key === 'streamers' => $this->withRoles(['streamer']),
            str_starts_with($key, 'role:') => $this->withRoles([substr($key, 5)]),
            default => new Collection(),
        };
    }

    /**
     * Users holding any of these roles, tolerating a role that is not there.
     *
     * User::role() throws RoleDoesNotExist for a name with no row behind it, so
     * renaming or deleting a role would otherwise have stopped every
     * notification addressed to it with nothing but a line in the log.
     */
    private function withRoles(array $names): Collection
    {
        $existing = Role::whereIn('name', $names)->where('guard_name', 'web')->pluck('name')->all();
        return $existing === [] ? new Collection() : User::role($existing)->get();
    }

    public static function modeLabels(): array
    {
        return ['all' => 'All Users', 'admins' => 'Admins Only', 'custom' => 'Specific Users'];
    }
}
