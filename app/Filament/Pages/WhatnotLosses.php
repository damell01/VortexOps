<?php

namespace App\Filament\Pages;

use App\Services\WhatnotLossReport;
use App\Services\Notifier;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class WhatnotLosses extends ProfitLoss
{
    protected static ?string $title = 'Whatnot Losses';
    protected static ?string $slug = 'whatnot-losses';
    #[Url] public string $from = '';
    #[Url] public string $to = '';
    public int $lossPage = 1;
    public function updated(): void { $this->lossPage = 1; }
    public function mount(): void
    {
        $this->from = $this->from ?: \App\Support\BusinessTime::local()->startOfWeek()->toDateString();
        $this->to = $this->to ?: \App\Support\BusinessTime::local()->toDateString();
    }
    public function getView(): string { return 'filament.pages.whatnot-losses'; }
    public function getSubheading(): ?string { return 'Negative Whatnot estimated earnings. Business costs and payroll are not included.'; }
    public function lossRows()
    {
        $this->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'channel' => 'nullable|integer']);
        return app(WhatnotLossReport::class)->rows(Carbon::parse($this->from), Carbon::parse($this->to), $this->channel !== '' ? (int) $this->channel : null);
    }
    public function sendSummaryAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('sendSummary')->label('Send Summary')->requiresConfirmation()
            ->modalHeading('Send Whatnot loss summary?')
            ->modalDescription('Uses your saved daily Whatnot-loss recipients and delivery preferences.')
            ->action(fn () => $this->sendSummary());
    }
    public function sendSummary(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->lossRows();
        $n = app(WhatnotLossReport::class)->notification('daily_whatnot_losses', Carbon::parse($this->from), Carbon::parse($this->to), $this->channel !== '' ? (int) $this->channel : null);
        if (!$n) { Notification::make()->title('No negative Whatnot shows in this range')->info()->send(); return; }
        $sent = Notifier::send('daily_whatnot_losses', $n);
        Notification::make()->title('Summary processed')->body("Routed to {$sent} users using saved notification preferences. Check the delivery log for email results.")->success()->send();
    }
}
