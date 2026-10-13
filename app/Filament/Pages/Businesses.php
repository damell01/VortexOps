<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\Streamer;
use App\Support\BusinessContext;
use App\Support\PaymentStructure;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Who works for which business, on one board: a column per business plus
 * "Not assigned". Drag a card (or every ticked card) onto a column, or tick
 * people and use the move buttons, instead of opening each profile.
 */
class Businesses extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Businesses';
    protected static ?string $navigationLabel = 'Businesses';
    protected static ?string $slug = 'businesses';

    public string $search = '';
    public string $memberType = 'all';
    public bool $showInactive = false;

    /** @var list<int> */
    public array $selected = [];

    public ?int $editingBusinessId = null;
    public array $businessForm = ['name' => '', 'short_name' => '', 'color' => '#7c3aed'];

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-building-office-2'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Payroll'; }
    public static function getNavigationSort(): ?int { return 3; }
    public static function canAccess(): bool { $u = auth()->user(); return ($u?->isAdmin() || $u?->isOwner() || $u?->isFulfillmentAdmin()) ?? false; }
    public function getView(): string { return 'filament.pages.businesses'; }

    public function getSubheading(): ?string
    {
        return 'Each person works for one business. Drag people between columns, or tick several and move them together. Payroll, pay runs and the dashboard follow the Business picker at the top.';
    }

    /** @return Collection<int, Business> */
    public function businesses(): Collection
    {
        return Business::query()->withCount('streamers')->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<string, Collection<int, Streamer>> keyed by business id, '' for not assigned */
    public function columns(): array
    {
        $members = Streamer::query()
            ->when(! $this->showInactive, fn ($q) => $q->where('status', 'active'))
            ->when($this->memberType === 'streamer', fn ($q) => $q->streamers())
            ->when($this->memberType === 'fulfillment', fn ($q) => $q->fulfillment())
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($this->search) . '%'))
            ->with(['channel:id,name', 'user:id,name'])
            ->orderBy('name')
            ->get();

        $columns = ['' => collect()];
        foreach ($this->businesses() as $business) {
            $columns[(string) $business->id] = collect();
        }
        foreach ($members as $member) {
            $key = $member->business_id && array_key_exists((string) $member->business_id, $columns) ? (string) $member->business_id : '';
            $columns[$key]->push($member);
        }

        return $columns;
    }

    /** "8% profit share + $15/hr" — what the person is paid, in a few words. */
    public function payLabel(Streamer $member): string
    {
        $resolved = PaymentStructure::resolve($member);
        $e = $resolved['effective'];
        $money = fn ($v) => '$' . rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';

        $label = match ($e['payout_type'] ?? null) {
            'profit_share' => $pct($e['payout_percentage'] ?? 0) . ' profit share',
            'sales_split' => 'Keeps ' . $pct(100 - (float) ($e['house_cut_percentage'] ?? 0)) . ' of ' . (($e['sales_split_basis'] ?? 'net') === 'gross' ? 'gross sales' : 'estimated net earnings'),
            'hourly' => $money($e['hourly_rate'] ?? 0) . '/hr',
            'pwe_labels' => 'PWE + labels',
            'package', 'flat_rate' => $money($e['package_rate'] ?? 0) . ' flat',
            'hybrid' => 'Hybrid',
            'custom_formula' => 'Custom formula',
            default => 'Pay not set',
        };

        if (($rate = $member->paidHourlyRate()) !== null) {
            $label .= ' + ' . $money($rate) . '/hr';
        }

        return $label;
    }

    /**
     * Put people in a business ($businessId null = take them out of one).
     *
     * @param  list<int|string>  $ids
     */
    public function assign(array $ids, ?int $businessId): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $business = $businessId ? Business::find($businessId) : null;
        if ($businessId && ! $business) {
            return;
        }

        $members = Streamer::whereKey($ids)->with('business:id,name')->get();
        $moved = 0;
        foreach ($members as $member) {
            if ((int) $member->business_id === (int) $businessId) {
                continue;
            }
            $before = $member->business?->name;
            $member->business_id = $businessId;
            $member->save();
            $moved++;

            $activity = activity('business')->performedOn($member)
                ->withProperties(['from' => $before, 'to' => $business?->name]);
            if ($user = auth()->user()) {
                $activity->causedBy($user);
            }
            $activity->log('Moved to ' . ($business?->name ?? 'no business'));
        }

        $this->selected = [];
        BusinessContext::flushMemo();

        Notification::make()
            ->title($moved === 0 ? 'Nothing to move' : ($moved === 1 ? '1 person' : "{$moved} people") . ' moved to ' . ($business?->name ?? 'Not assigned'))
            ->success()
            ->send();
    }

    public function moveSelected(?int $businessId): void
    {
        $this->assign($this->selected, $businessId);
    }

    /** @param list<int|string> $ids */
    public function selectColumn(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $allSelected = array_diff($ids, $this->selected) === [];
        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function newBusiness(): void
    {
        $this->editingBusinessId = 0;
        $this->businessForm = ['name' => '', 'short_name' => '', 'color' => '#2563eb'];
    }

    public function editBusiness(int $id): void
    {
        $business = Business::findOrFail($id);
        $this->editingBusinessId = $business->id;
        $this->businessForm = ['name' => $business->name, 'short_name' => (string) $business->short_name, 'color' => $business->color];
    }

    public function cancelBusiness(): void
    {
        $this->editingBusinessId = null;
    }

    public function saveBusiness(): void
    {
        $data = $this->validate([
            'businessForm.name' => 'required|string|max:120',
            'businessForm.short_name' => 'nullable|string|max:20',
            'businessForm.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ])['businessForm'];

        $data['short_name'] = trim((string) ($data['short_name'] ?? '')) ?: null;

        if ($this->editingBusinessId) {
            Business::findOrFail($this->editingBusinessId)->update($data);
        } else {
            Business::create($data + ['sort_order' => (int) Business::max('sort_order') + 1]);
        }

        $this->editingBusinessId = null;
        BusinessContext::flushMemo();
        Notification::make()->title('Business saved')->success()->send();
    }

    public function deleteBusiness(int $id): void
    {
        $business = Business::withCount(['streamers', 'payRuns'])->findOrFail($id);
        if ($business->streamers_count > 0 || $business->pay_runs_count > 0) {
            Notification::make()->title("{$business->name} can't be deleted")
                ->body('It still has people or Pay Runs. Move its people out first; a business with Pay Runs stays for the record.')
                ->danger()->send();

            return;
        }

        $business->delete();
        if (BusinessContext::currentId() === $id) {
            BusinessContext::setActive(null);
        }
        $this->editingBusinessId = null;
        BusinessContext::flushMemo();
        Notification::make()->title('Business deleted')->success()->send();
    }
}
