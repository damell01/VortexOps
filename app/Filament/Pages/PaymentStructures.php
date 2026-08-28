<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Streamer;
use App\Support\PaymentStructure;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PaymentStructures extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Payment Structures';
    protected static ?string $navigationLabel = 'Payment Structures';
    protected static ?string $slug = 'payment-structures';

    public array $streamer = [];
    public array $fulfillment = [];
    public bool $payroll_auto_setup_enabled = false;
    public bool $payroll_auto_recalculate_drafts = true;
    public bool $payroll_include_zero_activity = false;

    public ?int $editing_member_id = null;
    public array $member_override_enabled = [];
    public array $member_override_values = [];

    public function mount(): void
    {
        $this->streamer = $this->structureState('streamer', [
            'payout_type' => 'profit_share', 'payout_cadence' => 'weekly',
            'payout_percentage' => null, 'package_rate' => null, 'hourly_rate' => null,
            'pwe_rate' => null, 'label_rate' => null, 'include_tips' => true,
            'custom_payout_formula' => null,
        ]);
        $this->fulfillment = $this->structureState('fulfillment', [
            'payout_type' => 'pwe_labels', 'payout_cadence' => 'weekly',
            'payout_percentage' => null, 'package_rate' => null, 'hourly_rate' => null,
            'pwe_rate' => null, 'label_rate' => null, 'include_tips' => false,
            'custom_payout_formula' => null,
        ]);

        $this->payroll_auto_setup_enabled = Setting::getBool('payroll_auto_setup_enabled', false);
        $this->payroll_auto_recalculate_drafts = Setting::getBool('payroll_auto_recalculate_drafts', true);
        $this->payroll_include_zero_activity = Setting::getBool('payroll_include_zero_activity', false);
    }

    private function structureState(string $type, array $fallback): array
    {
        return array_merge($fallback, PaymentStructure::defaults($type));
    }

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }
    public function getView(): string { return 'filament.pages.payment-structures'; }

    public function save(): void
    {
        $rules = [];
        foreach (['streamer', 'fulfillment'] as $type) {
            $rules += [
                "$type.payout_type" => 'required|in:' . implode(',', array_keys(Streamer::payoutTypeLabels())),
                "$type.payout_cadence" => 'required|in:weekly,monthly',
                "$type.payout_percentage" => 'nullable|numeric|min:0|max:100',
                "$type.package_rate" => 'nullable|numeric|min:0',
                "$type.hourly_rate" => 'nullable|numeric|min:0',
                "$type.pwe_rate" => 'nullable|numeric|min:0',
                "$type.label_rate" => 'nullable|numeric|min:0',
                "$type.include_tips" => 'boolean',
                "$type.custom_payout_formula" => 'nullable|string|max:1000',
            ];
        }
        $this->validate($rules);

        PaymentStructure::saveDefaults('streamer', $this->streamer);
        PaymentStructure::saveDefaults('fulfillment', $this->fulfillment);
        Setting::set('payroll_auto_setup_enabled', $this->payroll_auto_setup_enabled ? '1' : '0');
        Setting::set('payroll_auto_recalculate_drafts', $this->payroll_auto_recalculate_drafts ? '1' : '0');
        Setting::set('payroll_include_zero_activity', $this->payroll_include_zero_activity ? '1' : '0');

        Notification::make()->title('Payment structures saved')->success()->send();
    }

    public function getStructureStatsProperty(): array
    {
        $stats = [];
        foreach (['streamer', 'fulfillment'] as $type) {
            $members = ($type === 'fulfillment' ? Streamer::fulfillment() : Streamer::streamers())
                ->get(['id', 'compensation_override_fields']);

            $legacy = $members->filter(fn (Streamer $member) => $member->compensation_override_fields === null)->count();
            $custom = $members->filter(fn (Streamer $member) => is_array($member->compensation_override_fields) && count($member->compensation_override_fields) > 0)->count();

            $stats[$type] = [
                'total' => $members->count(),
                'custom' => $custom,
                'inheriting' => max(0, $members->count() - $custom - $legacy),
                'legacy' => $legacy,
            ];
        }
        return $stats;
    }

    public function getMembersProperty()
    {
        return Streamer::query()->orderBy('member_type')->orderBy('name')->get();
    }

    public function editMember(int $id): void
    {
        $member = Streamer::findOrFail($id);
        $resolved = PaymentStructure::resolve($member);
        $this->editing_member_id = $id;
        $this->member_override_enabled = [];
        $this->member_override_values = [];

        foreach (['payout_type','payout_cadence','payout_percentage','hourly_rate','pwe_rate','label_rate','package_rate','include_tips','custom_payout_formula'] as $field) {
            $this->member_override_enabled[$field] = ! $resolved['legacy'] && array_key_exists($field, $resolved['overrides']);
            $this->member_override_values[$field] = $resolved['effective'][$field] ?? null;
        }
    }

    public function closeMemberEditor(): void
    {
        $this->editing_member_id = null;
        $this->member_override_enabled = [];
        $this->member_override_values = [];
    }

    public function adoptDefaults(int $id): void
    {
        $member = Streamer::findOrFail($id);
        PaymentStructure::adoptDefaults($member);
        Notification::make()->title($member->name . ' now inherits team defaults')->success()->send();
    }

    public function saveMemberOverrides(): void
    {
        $member = Streamer::findOrFail($this->editing_member_id);
        $overrides = [];
        foreach ($this->member_override_enabled as $field => $enabled) {
            if ($enabled) {
                $overrides[$field] = $this->member_override_values[$field] ?? null;
            }
        }
        PaymentStructure::saveOverrides($member, $overrides);
        $this->closeMemberEditor();
        Notification::make()->title('Individual compensation saved')->success()->send();
    }

    public function resetMemberOverrides(int $id): void
    {
        $member = Streamer::findOrFail($id);
        PaymentStructure::resetOverrides($member);
        Notification::make()->title('Overrides removed; team defaults restored')->success()->send();
    }
}
