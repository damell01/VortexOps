<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Streamer;
use App\Support\AdminModules;
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

    public function mount(): void
    {
        $this->streamer = $this->structureState('streamer', [
            'payout_type' => 'profit_share',
            'payout_cadence' => 'weekly',
            'payout_percentage' => null,
            'package_rate' => null,
            'hourly_rate' => null,
            'pwe_rate' => null,
            'label_rate' => null,
            'include_tips' => true,
            'custom_payout_formula' => null,
        ]);

        $this->fulfillment = $this->structureState('fulfillment', [
            'payout_type' => 'pwe_labels',
            'payout_cadence' => 'weekly',
            'payout_percentage' => null,
            'package_rate' => null,
            'hourly_rate' => null,
            'pwe_rate' => null,
            'label_rate' => null,
            'include_tips' => false,
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

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.payment-structures';
    }

    public function save(): void
    {
        $this->validate([
            'streamer.payout_type' => 'required|in:' . implode(',', array_keys(Streamer::payoutTypeLabels())),
            'streamer.payout_cadence' => 'required|in:weekly,monthly',
            'streamer.payout_percentage' => 'nullable|numeric|min:0|max:100',
            'streamer.package_rate' => 'nullable|numeric|min:0',
            'streamer.hourly_rate' => 'nullable|numeric|min:0',
            'streamer.pwe_rate' => 'nullable|numeric|min:0',
            'streamer.label_rate' => 'nullable|numeric|min:0',
            'streamer.include_tips' => 'boolean',
            'streamer.custom_payout_formula' => 'nullable|string|max:1000',
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
            $query = $type === 'fulfillment' ? Streamer::fulfillment() : Streamer::streamers();
            $total = (clone $query)->count();
            $legacy = (clone $query)->whereNull('compensation_override_fields')->count();
            $custom = (clone $query)->whereNotNull('compensation_override_fields')
                ->whereRaw('JSON_LENGTH(compensation_override_fields) > 0')
                ->count();

            $stats[$type] = [
                'total' => $total,
                'custom' => $custom,
                'inheriting' => max(0, $total - $custom - $legacy),
                'legacy' => $legacy,
            ];
        }

        return $stats;
    }
}
