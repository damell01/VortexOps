<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\InventoryVisibility;
use App\Support\InventoryPopupPolicy;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class InventoryPopupSettings extends Page
{
    protected static ?string $title = 'Inventory Login Popups';
    protected string $view = 'filament.pages.inventory-popup-settings';

    public bool $enabled = false;
    public array $roles = ['streamer'];
    public array $selectedStreamers = [];
    public string $streamerSearch = '';

    public function getStreamersProperty(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email'])
            ->filter(fn (User $user) => InventoryVisibility::isReadOnlyStreamer($user))
            ->filter(fn (User $user) => $this->streamerSearch === '' || str_contains(mb_strtolower($user->name.' '.$user->email), mb_strtolower(trim($this->streamerSearch))))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values()->all();
    }

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
        $this->selectedStreamers = array_map('intval', json_decode((string) Setting::get(InventoryPopupPolicy::USER_IDS, '[]'), true) ?: []);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $validRoles = ['streamer'];
        $this->roles = array_values(array_intersect($validRoles, $this->roles));
        $ids = array_values(array_unique(array_map('intval', $this->selectedStreamers)));
        $validIds = User::query()->whereIn('id', $ids)->get()->filter(fn (User $user) => InventoryVisibility::isReadOnlyStreamer($user))->pluck('id')->all();
        if (count($validIds) !== count($ids)) {
            $this->addError('selectedStreamers', 'Choose valid streamer accounts only.');
            return;
        }
        Setting::set(InventoryPopupPolicy::ENABLED, $this->enabled ? '1' : '0');
        Setting::set(InventoryPopupPolicy::ROLES, json_encode($this->roles));
        Setting::set(InventoryPopupPolicy::USER_IDS, json_encode($validIds));
        Notification::make()->title('Inventory popup settings saved')->success()->send();
    }
}
