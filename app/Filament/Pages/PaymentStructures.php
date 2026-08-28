<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Streamer;
use App\Support\PaymentStructure;
use App\Support\ProfitShareFormula;
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
    public ?float $streamer_burden_per_shipment = null;
    public ?float $streamer_burden_per_hour = null;
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
            'payout_percentage' => 8, 'include_tips' => true, 'custom_payout_formula' => null,
        ]);
        $this->fulfillment = $this->structureState('fulfillment', [
            'payout_type' => 'pwe_labels', 'payout_cadence' => 'weekly',
            'payout_percentage' => null, 'package_rate' => null, 'hourly_rate' => null,
            'pwe_rate' => null, 'label_rate' => null, 'include_tips' => false,
            'custom_payout_formula' => null,
        ]);
        $this->streamer_burden_per_shipment = ProfitShareFormula::ratePerShipment() ?? ProfitShareFormula::SUGGESTED_PER_SHIPMENT;
        $this->streamer_burden_per_hour = ProfitShareFormula::ratePerHour() ?? ProfitShareFormula::SUGGESTED_PER_HOUR;
        $this->payroll_auto_setup_enabled = Setting::getBool('payroll_auto_setup_enabled', false);
        $this->payroll_auto_recalculate_drafts = Setting::getBool('payroll_auto_recalculate_drafts', true);
        $this->payroll_include_zero_activity = Setting::getBool('payroll_include_zero_activity', false);
    }

    private function structureState(string $type, array $fallback): array { return array_merge($fallback, PaymentStructure::defaults($type)); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function canAccess(): bool { $u = auth()->user(); return ($u?->isAdmin() || $u?->isOwner()) ?? false; }
    public function getView(): string { return 'filament.pages.payment-structures'; }

    public function save(): void
    {
        $this->validate([
            'streamer.payout_percentage' => 'required|numeric|min:0|max:100',
            'streamer.custom_payout_formula' => 'nullable|string|max:1000',
            'streamer_burden_per_shipment' => 'required|numeric|min:0',
            'streamer_burden_per_hour' => 'required|numeric|min:0',
            'fulfillment.payout_type' => 'required|in:' . implode(',', array_keys(Streamer::payoutTypeLabels())),
            'fulfillment.payout_cadence' => 'required|in:weekly,monthly',
            'fulfillment.payout_percentage' => 'nullable|numeric|min:0|max:100',
            'fulfillment.package_rate' => 'nullable|numeric|min:0',
            'fulfillment.hourly_rate' => 'nullable|numeric|min:0',
            'fulfillment.pwe_rate' => 'nullable|numeric|min:0',
            'fulfillment.label_rate' => 'nullable|numeric|min:0',
            'fulfillment.include_tips' => 'boolean',
            'fulfillment.custom_payout_formula' => 'nullable|string|max:1000',
        ]);

        $this->streamer['payout_type'] = empty(trim((string)($this->streamer['custom_payout_formula'] ?? ''))) ? 'profit_share' : 'custom_formula';
        $this->streamer['payout_cadence'] = 'weekly';
        PaymentStructure::saveDefaults('streamer', $this->streamer);
        PaymentStructure::saveDefaults('fulfillment', $this->fulfillment);
        Setting::set('payroll_burden_per_shipment', (string)$this->streamer_burden_per_shipment);
        Setting::set('payroll_burden_per_hour', (string)$this->streamer_burden_per_hour);
        Setting::set('payroll_auto_setup_enabled', $this->payroll_auto_setup_enabled ? '1' : '0');
        Setting::set('payroll_auto_recalculate_drafts', $this->payroll_auto_recalculate_drafts ? '1' : '0');
        Setting::set('payroll_include_zero_activity', $this->payroll_include_zero_activity ? '1' : '0');
        Notification::make()->title('Payment structures saved')->success()->send();
    }

    public function getMembersProperty() { return Streamer::query()->orderBy('member_type')->orderBy('name')->get(); }

    public function editMember(int $id): void
    {
        $member = Streamer::findOrFail($id);
        $resolved = PaymentStructure::resolve($member);
        $this->editing_member_id = $id;
        $this->member_override_enabled = [];
        $this->member_override_values = [];
        $fields = $member->isFulfillment()
            ? ['payout_type','payout_percentage','hourly_rate','pwe_rate','label_rate','package_rate','include_tips','custom_payout_formula']
            : ['payout_percentage','custom_payout_formula','include_tips'];
        foreach ($fields as $field) {
            $this->member_override_enabled[$field] = ! $resolved['legacy'] && array_key_exists($field, $resolved['overrides']);
            $this->member_override_values[$field] = $resolved['effective'][$field] ?? null;
        }
    }

    public function closeMemberEditor(): void { $this->editing_member_id = null; $this->member_override_enabled = []; $this->member_override_values = []; }
    public function adoptDefaults(int $id): void { $m=Streamer::findOrFail($id); PaymentStructure::adoptDefaults($m); Notification::make()->title($m->name.' now inherits team defaults')->success()->send(); }
    public function saveMemberOverrides(): void
    {
        $member = Streamer::findOrFail($this->editing_member_id);
        $overrides = [];
        foreach ($this->member_override_enabled as $field => $enabled) if ($enabled) $overrides[$field] = $this->member_override_values[$field] ?? null;
        if (! $member->isFulfillment()) {
            $overrides['payout_type'] = !empty(trim((string)($overrides['custom_payout_formula'] ?? ''))) ? 'custom_formula' : 'profit_share';
            $overrides['payout_cadence'] = 'weekly';
        }
        PaymentStructure::saveOverrides($member, $overrides);
        $this->closeMemberEditor();
        Notification::make()->title('Individual compensation saved')->success()->send();
    }
    public function resetMemberOverrides(int $id): void { $m=Streamer::findOrFail($id); PaymentStructure::resetOverrides($m); Notification::make()->title('Overrides removed; team defaults restored')->success()->send(); }
}
