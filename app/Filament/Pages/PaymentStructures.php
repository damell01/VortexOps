<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\PaySchedule;
use App\Support\PaymentStructure;
use App\Support\ProfitShareFormula;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PaymentStructures extends Page
{
    /** Secondary workspace tool: opened from its hub; direct access/permissions stay unchanged. */
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Rates & Adjustments';
    protected static ?string $navigationLabel = 'Rates & Adjustments';
    protected static ?string $slug = 'payment-structures';

    public array $streamer = [];
    public array $fulfillment = [];
    public ?float $streamer_burden_per_shipment = null;
    public ?float $streamer_burden_per_hour = null;
    public bool $payroll_auto_setup_enabled = false;
    public bool $payroll_auto_recalculate_drafts = true;
    public bool $payroll_include_zero_activity = false;
    public string $member_filter = 'streamer';
    public ?int $editing_member_id = null;
    public array $member_override_enabled = [];
    public array $member_override_values = [];
    public bool $show_archived = false;
    public int $payroll_week_start_day = 1;

    /** The business whose rates are being edited (the Business picker), or null for the shared rates. */
    public ?int $business_id = null;

    public function mount(): void
    {
        $this->business_id = \App\Support\BusinessContext::currentId();
        $this->streamer = $this->structureState('streamer', [
            'payout_type' => 'profit_share', 'payout_cadence' => 'weekly',
            'payout_percentage' => 8, 'include_tips' => true, 'custom_payout_formula' => null,
            'hourly_rate' => null, 'house_cut_percentage' => null, 'sales_split_basis' => 'net',
        ]);
        if (($this->streamer['payout_type'] ?? null) !== 'sales_split') {
            $this->streamer['payout_type'] = 'profit_share';
        }
        $this->streamer['sales_split_basis'] ??= 'net';
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
        $this->payroll_week_start_day = PaySchedule::startDay();
    }

    private function structureState(string $type, array $fallback): array { return array_merge($fallback, PaymentStructure::defaults($type, $this->business_id)); }

    public function getBusinessProperty(): ?\App\Models\Business
    {
        return $this->business_id ? \App\Models\Business::find($this->business_id) : null;
    }

    /** Whether this business still uses the shared rates (nothing saved for it yet). */
    public function getUsesSharedRatesProperty(): bool
    {
        return $this->business_id !== null && ! PaymentStructure::hasOwnDefaults('streamer', $this->business_id);
    }

    public function useSharedRates(): void
    {
        if (! $this->business_id) {
            return;
        }
        PaymentStructure::clearBusinessDefaults('streamer', $this->business_id);
        PaymentStructure::clearBusinessDefaults('fulfillment', $this->business_id);
        $this->mount();
        Notification::make()->title(($this->business?->name ?? 'This business') . ' now uses the shared rates')->success()->send();
    }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Payroll'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function canAccess(): bool { $u = auth()->user(); return ($u?->isAdmin() || $u?->isOwner()) ?? false; }
    public function getView(): string { return 'filament.pages.payment-structures'; }

    public function setMemberFilter(string $filter): void
    {
        $this->member_filter = in_array($filter, ['streamer', 'fulfillment'], true) ? $filter : 'streamer';
        $this->closeMemberEditor();
    }

    public function save(): void
    {
        $this->validate([
            'streamer.payout_type' => 'required|in:profit_share,sales_split',
            'streamer.house_cut_percentage' => 'nullable|required_if:streamer.payout_type,sales_split|numeric|min:0|max:100',
            'streamer.sales_split_basis' => 'nullable|in:net,gross',
            'streamer.payout_percentage' => 'required_unless:streamer.payout_type,sales_split|nullable|numeric|min:0|max:100',
            'streamer.custom_payout_formula' => 'nullable|string|max:1000',
            'streamer.hourly_rate' => 'nullable|numeric|min:0',
            'payroll_week_start_day' => 'required|integer|in:' . implode(',', array_keys(PaySchedule::dayOptions())),
            'streamer_burden_per_shipment' => 'required|numeric|min:0',
            'streamer_burden_per_hour' => 'required|numeric|min:0',
            'fulfillment.payout_type' => 'required|in:' . implode(',', array_keys(Streamer::assignablePayoutTypeLabels())),
            'fulfillment.payout_cadence' => 'required|in:weekly,monthly',
            'fulfillment.payout_percentage' => 'nullable|numeric|min:0|max:100',
            'fulfillment.package_rate' => 'nullable|numeric|min:0',
            'fulfillment.hourly_rate' => 'nullable|numeric|min:0',
            'fulfillment.pwe_rate' => 'nullable|numeric|min:0',
            'fulfillment.label_rate' => 'nullable|numeric|min:0',
            'fulfillment.include_tips' => 'boolean',
            'fulfillment.custom_payout_formula' => 'nullable|string|max:1000',
        ]);

        $streamer = $this->streamer;
        if ($streamer['payout_type'] !== 'sales_split' && trim((string) ($streamer['custom_payout_formula'] ?? '')) !== '') {
            $streamer['payout_type'] = 'custom_formula';
        }
        $streamer['payout_cadence'] = 'weekly';
        $this->fulfillment['payout_cadence'] = 'weekly';
        PaymentStructure::saveDefaults('streamer', $streamer, $this->business_id);
        PaymentStructure::saveDefaults('fulfillment', $this->fulfillment, $this->business_id);
        Setting::set('payroll_burden_per_shipment', (string)$this->streamer_burden_per_shipment);
        Setting::set('payroll_burden_per_hour', (string)$this->streamer_burden_per_hour);
        Setting::set('payroll_auto_setup_enabled', $this->payroll_auto_setup_enabled ? '1' : '0');
        Setting::set('payroll_auto_recalculate_drafts', $this->payroll_auto_recalculate_drafts ? '1' : '0');
        Setting::set('payroll_include_zero_activity', $this->payroll_include_zero_activity ? '1' : '0');
        Setting::set(PaySchedule::SETTING, (string) $this->payroll_week_start_day);
        Notification::make()->title('Payment structures saved')->success()->send();
    }

    public function getMembersProperty()
    {
        return Streamer::query()
            ->inBusinessContext()
            ->when(! $this->show_archived, fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'inactive')->orWhereNull('status')))
            ->orderBy('member_type')->orderBy('name')->get();
    }

    public function getFilteredMembersProperty()
    {
        return $this->members->filter(fn (Streamer $member) => $this->member_filter === 'fulfillment'
            ? $member->isFulfillment()
            : ! $member->isFulfillment());
    }

    public function editMember(int $id): void
    {
        $member = Streamer::findOrFail($id);
        $resolved = PaymentStructure::resolve($member);
        $this->editing_member_id = $id;
        $this->member_override_enabled = [];
        $this->member_override_values = [];
        $fields = $member->isFulfillment()
            ? ['payout_type','payout_percentage','hourly_rate','pwe_rate','label_rate','package_rate','include_tips','custom_payout_formula']
            : ['payout_type','payout_percentage','house_cut_percentage','sales_split_basis','hourly_rate','custom_payout_formula','include_tips'];
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
            // A streamer's pay type is their own only when it was chosen here
            // (or they have their own formula); otherwise it follows their
            // business's default, so moving business changes how they're paid.
            if (trim((string) ($overrides['custom_payout_formula'] ?? '')) !== '') {
                $overrides['payout_type'] = 'custom_formula';
            } elseif (isset($overrides['payout_type']) && ! in_array($overrides['payout_type'], ['profit_share', 'sales_split'], true)) {
                unset($overrides['payout_type']);
            }
        } else {
            $overrides['payout_cadence'] = 'weekly';
        }
        PaymentStructure::saveOverrides($member, $overrides);
        $this->closeMemberEditor();
        Notification::make()->title('Individual compensation saved')->success()->send();
    }

    /** The current pay period and the one after it, as saved. */
    public function getSchedulePreviewProperty(): array
    {
        $current = PaySchedule::periodFor(now());
        $next = PaySchedule::periodFor($current[1]->copy()->addDay());

        return [
            'current' => PaySchedule::label($current[0], $current[1]),
            'next' => PaySchedule::label($next[0], $next[1]),
            'changed' => (int) $this->payroll_week_start_day !== PaySchedule::startDay(),
        ];
    }

    public function setShowArchived(bool $show): void
    {
        $this->show_archived = $show;
        $this->closeMemberEditor();
    }

    public function removeMember(int $id): void
    {
        $member = Streamer::findOrFail($id);
        $result = app(\App\Services\TeamProfileRemoval::class)->remove($member);
        $this->closeMemberEditor();
        Notification::make()
            ->title($result === 'deleted' ? $member->name . ' removed' : $member->name . ' archived')
            ->body($result === 'deleted'
                ? 'The profile had no shows or pay history, so it was deleted.'
                : 'The profile has past shows or pay, so it was archived: it is off payroll and new Pay Runs, and past runs keep the name. Use "Show archived" to restore it.')
            ->success()->send();
    }

    public function restoreMember(int $id): void
    {
        $member = Streamer::findOrFail($id);
        app(\App\Services\TeamProfileRemoval::class)->restore($member);
        Notification::make()->title($member->name . ' is back on payroll')->success()->send();
    }

    public function resetMemberOverrides(int $id): void { $m=Streamer::findOrFail($id); PaymentStructure::resetOverrides($m); Notification::make()->title('Overrides removed; team defaults restored')->success()->send(); }
}
