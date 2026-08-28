<?php

namespace App\Filament\Pages;

use App\Services\PayRunAutomationService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PayRunBackfill extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Pay Run Backfill';
    protected static ?string $navigationLabel = 'Pay Run Backfill';
    protected static ?string $slug = 'pay-run-backfill';

    public string $from_date = '';
    public string $to_date = '';
    public string $member_type = '';

    /** @var array<int,array<string,mixed>> */
    public array $results = [];

    public function mount(): void
    {
        $this->from_date = now()->subWeeks(8)->startOfWeek()->toDateString();
        $this->to_date = now()->endOfWeek()->toDateString();
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-path-rounded-square';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('payouts');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.pay-run-backfill';
    }

    public function preview(PayRunAutomationService $automation): void
    {
        $this->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'member_type' => 'nullable|in:streamer,fulfillment',
        ]);

        $this->results = $automation->previewRange(
            $this->from_date,
            $this->to_date,
            $this->member_type ?: null,
        );

        Notification::make()
            ->title('Backfill preview complete')
            ->body(count($this->results) . ' weekly period(s) compared. No payroll records were changed.')
            ->success()
            ->send();
    }

    public function applySafe(PayRunAutomationService $automation): void
    {
        $this->preview($automation);

        $changed = 0;
        $skipped = 0;
        foreach ($this->results as $row) {
            if ($row['read_only']) {
                $skipped++;
                continue;
            }

            $automation->syncWeek($row['week_start']);
            $changed++;
        }

        $this->results = $automation->previewRange(
            $this->from_date,
            $this->to_date,
            $this->member_type ?: null,
        );

        Notification::make()
            ->title('Safe backfill complete')
            ->body("{$changed} missing/Draft week(s) synced; {$skipped} finalized/submitted/paid week(s) left unchanged.")
            ->success()
            ->send();
    }
}
