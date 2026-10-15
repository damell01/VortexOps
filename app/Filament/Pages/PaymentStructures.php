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
    public bool $payroll_auto_recalculate_drafts = true;
    public bool $payroll_include_zero_activity = false;
    public string $member_filter = 'streamer';
    public string $member_search = '';
    public string $pay_method_filter = '';
    public int $member_page = 1;
    public ?int $editing_member_id = null;
    public array $member_override_enabled = [];
    public array $member_override_values = [];
    public bool $show_archived = false;
    public int $payroll_week_start_day = 1;

    /** The business whose rates are being edited (the Business picker), or null for the shared rates. */
    public ?int $business_id = null;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('save_rates')->label('Save changes')->icon('heroicon-o-check')->color('gray')->action(fn () => $this->save()),
            \Filament\Actions\Action::make('new_pay_run')->label('New Pay Run')->icon('heroicon-o-plus')
                ->url(\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('create'))
                ->visible(fn () => auth()->user()?->can('create', \App\Models\WeeklyPayoutBatch::class)),
            \Filament\Actions\Action::make('pay_runs')->label('View Pay Runs')->color('gray')->icon('heroicon-o-banknotes')
                ->url(\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index')),
        ];
    }

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
        $this->payroll_auto_recalculate_drafts = Setting::getBool('payroll_auto_recalculate_drafts', true);
        $this->payroll_include_zero_activity = Setting::getBool('payroll_include_zero_activity', false);
        $this->payroll_week_start_day = PaySchedule::startDay();
        $this->run_types = \App\Models\PayRunType::orderBy('id')->get()->map(fn ($type) => [
            'id' => $type->id, 'name' => $type->name, 'kind' => $type->kind ?? '', 'active' => $type->active,
            'schedule_enabled' => $type->schedule_enabled, 'frequency' => $type->frequency,
            'anchor' => $type->anchor?->toDateString(), 'auto_create' => $type->auto_create,
        ])->all();
    }

    public array $run_types = [];

    /** Team rates are edited in a pop-up, the way an individual override is. */
    public bool $editing_rates = false;

    public function openRatesEditor(): void
    {
        $this->resetErrorBag();
        $this->editing_rates = true;
    }

    /** Close without saving: put back the rates as they were saved. */
    public function cancelRatesEditor(): void
    {
        $saved = new static();
        $saved->mount();
        $this->streamer = $saved->streamer;
        $this->fulfillment = $saved->fulfillment;
        $this->streamer_burden_per_shipment = $saved->streamer_burden_per_shipment;
        $this->streamer_burden_per_hour = $saved->streamer_burden_per_hour;
        $this->resetErrorBag();
        $this->editing_rates = false;
    }

    public function saveRates(): void
    {
        $this->save();
        $this->editing_rates = false;
    }

    public function addRunType(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->run_types[] = ['id' => null, 'name' => '', 'kind' => 'wages', 'active' => true,
            'schedule_enabled' => false, 'frequency' => 'weekly', 'anchor' => null, 'auto_create' => false];
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
        $this->member_page = 1;
        $this->closeMemberEditor();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'streamer.payout_type' => 'required|in:profit_share,sales_split',
            'streamer.house_cut_percentage' => 'nullable|required_if:streamer.payout_type,sales_split|numeric|min:0|max:100',
            'streamer.sales_split_basis' => 'nullable|in:net,gross',
            'streamer.payout_percentage' => 'required_unless:streamer.payout_type,sales_split|nullable|numeric|min:0|max:100',
            'streamer.custom_payout_formula' => 'nullable|string|max:1000',
            'streamer.hourly_rate' => 'nullable|numeric|min:0',
            'payroll_week_start_day' => 'required|integer|in:' . implode(',', array_keys(PaySchedule::dayOptions())),
            'run_types' => 'array|max:50',
            'run_types.*.id' => 'nullable|integer|exists:pay_run_types,id',
            'run_types.*.name' => 'required|string|max:100|regex:/\\S/',
            'run_types.*.kind' => 'nullable|in:' . implode(',', array_keys(\App\Support\PayRunSchedules::KINDS)),
            'run_types.*.active' => 'required|boolean',
            'run_types.*.schedule_enabled' => 'required|boolean',
            'run_types.*.frequency' => 'required|in:' . implode(',', array_keys(\App\Support\PayRunSchedules::FREQUENCIES)),
            'run_types.*.anchor' => 'nullable|date',
            'run_types.*.auto_create' => 'required|boolean',
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
        Setting::set('payroll_auto_recalculate_drafts', $this->payroll_auto_recalculate_drafts ? '1' : '0');
        Setting::set('payroll_include_zero_activity', $this->payroll_include_zero_activity ? '1' : '0');
        Setting::set(PaySchedule::SETTING, (string) $this->payroll_week_start_day);
        \Illuminate\Support\Facades\DB::transaction(function () {
            foreach ($this->run_types as $row) {
                $type = filled($row['id'] ?? null) ? \App\Models\PayRunType::findOrFail($row['id']) : new \App\Models\PayRunType;
                $type->fill([
                    'name' => trim($row['name']), 'kind' => filled($row['kind']) ? $row['kind'] : null,
                    'active' => $row['active'], 'schedule_enabled' => $row['schedule_enabled'],
                    'frequency' => $row['frequency'], 'anchor' => $row['anchor'] ?: null,
                    'auto_create' => $row['schedule_enabled'] && $row['active'] && $row['auto_create'],
                ])->save();
            }
        });
        $this->mount();
        Notification::make()->title('Payment structures saved')->success()->send();
    }

    public function getMembersProperty()
    {
        return Streamer::query()
            ->inBusinessContext()
            ->when(! $this->show_archived, fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'inactive')->orWhereNull('status')))
            ->when(trim($this->member_search) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->member_search) . '%'))
            ->with(['channel', 'business', 'user'])
            ->orderBy('member_type')->orderBy('name')->get();
    }

    public function getFilteredMembersProperty()
    {
        return $this->members->filter(fn (Streamer $member) => $this->member_filter === 'fulfillment'
            ? $member->isFulfillment()
            : ! $member->isFulfillment())->filter(fn (Streamer $member) => $this->pay_method_filter === '' || ($member->effectiveCompensation()['effective']['payout_type'] ?? null) === $this->pay_method_filter);
    }

    public function updatedMemberSearch(): void { $this->member_page = 1; }
    public function updatedPayMethodFilter(): void { $this->member_page = 1; }
    public function setMemberPage(int $page): void { $this->member_page = max(1, min($page, max(1, (int) ceil($this->filteredMembers->count() / 10)))); }

    public function getPayrollSummaryProperty(): array
    {
        return [
            'people' => Streamer::query()->inBusinessContext()->where('status', 'active')->count(),
            'draft_total' => \App\Models\WeeklyPayoutBatch::query()->inBusinessContext()->where('status', 'draft')->sum('total_payout'),
            'scheduled' => \App\Models\PayRunType::where('active', true)->where('schedule_enabled', true)->count(),
            'types' => \App\Models\PayRunType::where('active', true)->count(),
        ];
    }

    public function getRosterRowsProperty(): \Illuminate\Support\Collection
    {
        $members = $this->filteredMembers;
        $page = max(1, min($this->member_page, max(1, (int) ceil($members->count() / 10))));
        return $members->slice(($page - 1) * 10, 10)->map(function (Streamer $member) {
            $pay = $member->effectiveCompensation();
            $effective = $pay['effective'];
            $kind = $effective['payout_type'] ?? '';
            $method = match ($kind) {
                'profit_share' => 'Show formula', 'sales_split' => 'Sales earnings', 'hourly' => 'Hourly wages', 'shift_share' => 'Hourly + shipments + share',
                default => Streamer::payoutTypeLabels()[$kind] ?? 'Custom calculation',
            };
            $rate = match ($kind) {
                'hourly' => '$' . number_format((float) ($effective['hourly_rate'] ?? 0), 2) . ' / hour',
                'sales_split' => number_format(100 - (float) ($effective['house_cut_percentage'] ?? 0), 2) . '% of earnings',
                'profit_share' => number_format((float) ($effective['payout_percentage'] ?? 0), 2) . '% · show formula',
                'package' => '$' . number_format((float) ($effective['package_rate'] ?? 0), 2) . ' / package',
                'shift_share' => '$' . number_format((float) ($effective['hourly_rate'] ?? 0), 2) . ' / hr + shipments + ' . number_format((float) ($effective['payout_percentage'] ?? 0), 2) . '% share',
                default => 'Configured calculation',
            };
            if ($member->paidHourlyRate() !== null) $rate .= ' + $' . number_format($member->paidHourlyRate(), 2) . ' / hr';
            return ['member' => $member, 'method' => $method, 'rate' => $rate,
                'initials' => collect(explode(' ', trim($member->name)))->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->join(''),
                'override' => count($pay['overrides'] ?? []) > 0];
        });
    }

    public function deleteRunType(int $index): void
    {
        abort_unless(static::canAccess(), 403);
        if (! array_key_exists($index, $this->run_types)) return;
        $id = $this->run_types[$index]['id'] ?? null;
        if ($id) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $type = \App\Models\PayRunType::lockForUpdate()->findOrFail($id);
                \App\Models\WeeklyPayoutBatch::where('pay_run_type_id', $id)->whereNull('pay_run_type_name')->update(['pay_run_type_name' => $type->name]);
                $type->delete();
            });
        }
        array_splice($this->run_types, $index, 1);
        Notification::make()->title('Pay run type deleted')->body('Existing pay runs keep their names, dates and pay coverage.')->success()->send();
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
            : ['payout_type','payout_percentage','house_cut_percentage','sales_split_basis','hourly_rate','pwe_rate','label_rate','burden_rate_type','burden_rate_value','custom_payout_formula','include_tips'];
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
        // The burden's type travels with its amount.
        if (array_key_exists('burden_rate_value', $overrides)) {
            $overrides['burden_rate_type'] = ($this->member_override_values['burden_rate_type'] ?? 'flat') === 'percentage' ? 'percentage' : 'flat';
        } else {
            unset($overrides['burden_rate_type']);
        }
        if (! $member->isFulfillment()) {
            // A streamer's pay type is their own only when it was chosen here
            // (or they have their own formula); otherwise it follows their
            // business's default, so moving business changes how they're paid.
            if (trim((string) ($overrides['custom_payout_formula'] ?? '')) !== '') {
                $overrides['payout_type'] = 'custom_formula';
            } elseif (isset($overrides['payout_type']) && ! in_array($overrides['payout_type'], ['profit_share', 'sales_split', 'hourly', 'shift_share'], true)) {
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
        $this->member_page = 1;
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
