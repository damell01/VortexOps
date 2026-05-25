<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationRouter
{
    private const DEFAULTS = [
        'low_stock'       => 'all',
        'damaged'         => 'all',
        'show_ready'      => 'admins',
        'show_reconciled' => 'admins',
    ];

    /**
     * Returns the users who should receive a given notification type.
     * Types: low_stock, damaged, show_ready, show_reconciled
     */
    public function getRecipients(string $type): Collection
    {
        $mode = Setting::get("notify_{$type}_mode", self::DEFAULTS[$type] ?? 'admins');

        return match ($mode) {
            'all'    => User::all(),
            'admins' => User::role(['admin', 'super_admin'])->get(),
            'custom' => $this->customUsers($type),
            default  => User::role(['admin', 'super_admin'])->get(),
        };
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
