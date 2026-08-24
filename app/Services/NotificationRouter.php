<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class NotificationRouter
{
    private const DEFAULTS = [
        'low_stock'              => 'all',
        'damaged'                => 'all',
        'show_ready'             => 'admins',
        'show_reconciled'        => 'admins',
        'show_pending_approval'  => 'admins',
        'deduction_approved'     => 'admins',
        'weekly_review_reminder' => 'admins',
        'midweek_report'         => 'admins',
        'system_health'          => 'admins',
    ];

    /**
     * Returns the users who should receive a given notification type.
     * Types: low_stock, damaged, show_ready, show_reconciled, show_pending_approval,
     * weekly_review_reminder, midweek_report, system_health
     */
    public function getRecipients(string $type): Collection
    {
        $mode = Setting::get("notify_{$type}_mode", self::DEFAULTS[$type] ?? 'admins');

        return match ($mode) {
            'all'    => User::all(),
            'custom' => $this->customUsers($type),
            default  => $this->admins(),
        };
    }

    /**
     * Everyone who counts as an admin, tolerating a role that is not there.
     *
     * User::role() throws RoleDoesNotExist for a name with no row behind it,
     * and every caller of this wraps the dispatch in a try/catch that logs a
     * warning — so renaming or deleting `super_admin` would have stopped every
     * admin notification in the application with nothing to show for it but a
     * line in the log nobody reads.
     */
    private function admins(): Collection
    {
        $names = Role::whereIn('name', ['admin', 'super_admin'])
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        return $names === [] ? new Collection() : User::role($names)->get();
    }

    public static function modeLabels(): array
    {
        return [
            'all'    => 'All Users',
            'admins' => 'Admins Only',
            'custom' => 'Specific Users',
        ];
    }

    private function customUsers(string $type): Collection
    {
        $ids = json_decode(Setting::get("notify_{$type}_users", '[]'), true) ?? [];

        if (empty($ids)) {
            return new Collection();
        }

        return User::whereIn('id', $ids)->get();
    }
}
