<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

final class InventoryPopupPolicy
{
    public const ENABLED = 'inventory_login_popup_enabled';
    public const ROLES = 'inventory_login_popup_roles';
    public const USER_IDS = 'inventory_login_popup_user_ids';

    public static function enabledFor(?User $user): bool
    {
        if (! $user || ! Setting::getBool(self::ENABLED, true)) return false;
        $roles = json_decode((string) Setting::get(self::ROLES, '["streamer"]'), true);
        $roles = is_array($roles) ? $roles : ['streamer'];
        $ids = json_decode((string) Setting::get(self::USER_IDS, '[]'), true);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];

        if (in_array($user->id, $ids, true)) return InventoryVisibility::isReadOnlyStreamer($user);
        if (in_array('streamer', $roles, true) && InventoryVisibility::isReadOnlyStreamer($user)) return true;
        return false;
    }
}
