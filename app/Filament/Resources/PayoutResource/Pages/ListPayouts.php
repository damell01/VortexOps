<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use App\Models\Payout;
use Illuminate\Support\Carbon;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayouts extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;

    protected static string $resource = PayoutResource::class;

    protected string $view = 'filament.resources.payout-resource.pages.list-payouts';

    public function payoutStats(): array
    {
        [$start, $end] = \App\Support\PaySchedule::periodFor(now());
        $end = $end->copy()->endOfDay();
        $query = PayoutResource::getEloquentQuery()->whereBetween('created_at', [$start, $end]);

        return [
            'total' => (float) (clone $query)->whereIn('status', ['draft', 'approved'])->sum('calculated_payout'),
            'streamers' => (int) (clone $query)->distinct('streamer_id')->count('streamer_id'),
            'deductions' => (float) (clone $query)->sum('loan_repayment_deducted'),
            'hold' => (int) (clone $query)->where('status', 'on_hold')->count(),
            'label' => $start->format('M j') . ' – ' . $end->format('M j'),
        ];
    }

    /** Quick filters, shown as chips above the list. */
    public function getTabs(): array
    {
        $count = fn (string $status) => PayoutResource::getEloquentQuery()->where('status', $status)->count() ?: null;
        [$start, $end] = \App\Support\PaySchedule::periodFor(now());

        return [
            'all' => Tab::make('All'),
            'review' => Tab::make('To review')->badge($count('draft'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
            'pay' => Tab::make('To pay')->badge($count('approved'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'paid' => Tab::make('Paid')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
            'week' => Tab::make('This pay week')->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('created_at', [$start, $end->copy()->endOfDay()])),
        ];
    }

    public function getSubheading(): ?string
    {
        return (auth()->user()?->isAdmin() ?? false)
            ? 'All streamer payouts. Admins now run pay runs from Payouts under Pay Runs — this flat list stays for reference and direct links.'
            : 'Your payout history — every show you were part of and what you earned or are owed.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->url(fn () => route('export.payouts'))
                ->openUrlInNewTab(),
        ];
    }
}
