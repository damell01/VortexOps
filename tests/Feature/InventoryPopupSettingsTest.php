<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\InventoryPopupPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPopupSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_popup_is_off_by_default(): void
    {
        $this->assertFalse(InventoryPopupPolicy::enabledFor(User::factory()->create()));
    }

    public function test_non_streamer_cannot_be_targeted_during_streamer_only_rollout(): void
    {
        $user = User::factory()->create();
        Setting::set(InventoryPopupPolicy::ENABLED, '1');
        Setting::set(InventoryPopupPolicy::ROLES, '["admin","fulfillment"]');
        Setting::set(InventoryPopupPolicy::USER_IDS, json_encode([$user->id]));

        $this->assertFalse(InventoryPopupPolicy::enabledFor($user));
    }

    public function test_disabling_popup_overrides_user_targeting(): void
    {
        $user = User::factory()->create();
        Setting::set(InventoryPopupPolicy::USER_IDS, json_encode([$user->id]));
        Setting::set(InventoryPopupPolicy::ENABLED, '0');

        $this->assertFalse(InventoryPopupPolicy::enabledFor($user));
    }
}
