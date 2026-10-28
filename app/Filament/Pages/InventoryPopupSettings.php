<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\InventoryPopupPolicy;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class InventoryPopupSettings extends Page
{
    protected static ?string $title = 'Inventory Login Popups';
    protected string $view = 'filament.pages.inventory-popup-settings';

    public bool $enabled = false;
    public array $roles = ['streamer'];
    public string $userIds = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() || auth()->user()?->isAdmin();
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bell-alert';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->enabled = Setting::getBool(InventoryPopupPolicy::ENABLED, false);
        $this->roles = json_decode((string) Setting::get(InventoryPopupPolicy::ROLES, '["streamer"]'), true) ?: ['streamer'];
        $this->userIds = implode(', ', json_decode((string) Setting::get(InventoryPopupPolicy::USER_IDS, '[]'), true) ?: []);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $validRoles = ['streamer', 'admin', 'fulfillment', 'streamer_assistant'];
        $this->roles = array_values(array_intersect($validRoles, $this->roles));
        $ids = trim($this->userIds) === '' ? [] : array_map('trim', explode(',', $this->userIds));
        if (count(array_filter($ids, fn ($id) => ! ctype_digit($id))) > 0) {
            $this->addError('userIds', 'Enter numeric user IDs separated by commas.');
            return;
        }
        Setting::set(InventoryPopupPolicy::ENABLED, $this->enabled ? '1' : '0');
        Setting::set(InventoryPopupPolicy::ROLES, json_encode($this->roles));
        Setting::set(InventoryPopupPolicy::USER_IDS, json_encode(array_values(array_unique(array_map('intval', $ids)))));
        Notification::make()->title('Inventory popup settings saved')->success()->send();
    }
}
