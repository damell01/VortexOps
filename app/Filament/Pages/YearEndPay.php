<?php

namespace App\Filament\Pages;

use App\Services\YearEndPaySummary;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** What each person was paid in a calendar year, ready for 1099s. */
class YearEndPay extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Year-end pay summary';
    protected static ?string $navigationLabel = 'Year-end pay (1099)';
    protected static ?string $slug = 'payroll/year-end';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Payroll';
    protected static ?int $navigationSort = 4;
    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'year')]
    public ?int $year = null;

    #[Url(as: 'min')]
    public ?string $minimum = null;

    public static function canAccess(): bool
    {
        return \App\Support\AdminModules::isEnabled('payouts') && (auth()->user()?->isAdmin() ?? false)
            && ! \App\Support\NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    public function mount(): void
    {
        // Early in the year people usually want last year's totals.
        $this->year ??= now()->month <= 3 ? now()->year - 1 : now()->year;
        $this->year = max(2020, min(now()->year, (int) $this->year));
    }

    public function getView(): string
    {
        return 'filament.pages.year-end-pay';
    }

    public function getSubheading(): ?string
    {
        return 'Pay marked paid in the calendar year, per person: totals for year-end tax forms such as 1099s. Check with your accountant who needs a form and the reporting threshold.';
    }

    private function min(): float
    {
        return max(0, (float) str_replace([',', '$'], '', (string) $this->minimum));
    }

    public function getViewData(): array
    {
        $rows = app(YearEndPaySummary::class)->for((int) $this->year, $this->min());

        return ['rows' => $rows, 'years' => range(now()->year, max(2020, now()->year - 5))];
    }

    public function export(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $rows = app(YearEndPaySummary::class)->for((int) $this->year, $this->min());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Legal name', 'Email', 'Business', 'Role', 'Total paid', 'Tips included', 'Other pay', 'Deductions', 'Runs', 'First paid', 'Last paid']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['legal_name'], $r['email'], $r['business'], $r['kind'], number_format($r['total'], 2, '.', ''), number_format($r['tips'], 2, '.', ''),
                    number_format($r['other'], 2, '.', ''), number_format($r['deductions'], 2, '.', ''), $r['runs'], $r['first_paid'], $r['last_paid']]);
            }
            fclose($out);
        }, 'year-end-pay-' . $this->year . '.csv', ['Content-Type' => 'text/csv']);
    }
}
