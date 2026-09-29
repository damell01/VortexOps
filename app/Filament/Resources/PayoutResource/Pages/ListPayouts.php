<?php

namespace App\Filament\Resources\PayoutResource\Pages;

use App\Filament\Resources\PayoutResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use App\Models\Payout;
use Illuminate\Support\Carbon;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected string $view = 'filament.resources.payout-resource.pages.list-payouts';

    public function payoutStats(): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();
        $query = PayoutResource::getEloquentQuery()->whereBetween('created_at', [$start, $end]);

        return [
            'total' => (float) (clone $query)->whereIn('status', ['draft', 'approved'])->sum('calculated_payout'),
            'streamers' => (int) (clone $query)->distinct('streamer_id')->count('streamer_id'),
            'deductions' => (float) (clone $query)->sum('loan_repayment_deducted'),
            'hold' => (int) (clone $query)->where('status', 'on_hold')->count(),
            'label' => $start->format('M j') . ' – ' . $end->format('M j'),
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
