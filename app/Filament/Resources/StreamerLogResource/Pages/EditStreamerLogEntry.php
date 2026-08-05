<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;
use App\Services\ShippingSurchargeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class EditStreamerLogEntry extends EditRecord
{
    protected static string $resource = StreamerLogResource::class;

    public function getView(): string
    {
        return 'filament.resources.streamer-log-resource.pages.edit-streamer-log-entry';
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $isStreamer = $user?->isStreamer() && !$user->isAdmin();
        $isAdmin = $user?->isAdmin();

        $actions = [];

        if ($isStreamer) {
            $actions[] = Action::make('request_edit')
                ->label('Request Edit Permission')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => StreamerLogResource::isLockedForCurrentUser($this->record))
                ->requiresConfirmation()
                ->modalHeading('Request Edit Permission')
                ->modalDescription('An admin will review your request and unlock this log entry for editing.')
                ->action(function () {
                    Notification::make()
                        ->title('Request sent')
                        ->body('An admin has been notified of your edit request.')
                        ->success()
                        ->send();
                });
        }

        if ($isAdmin) {
            $actions[] = Action::make('approve_report')
                ->label('Approve Report')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->isSubmitted() && $this->record->approval_status !== 'approved')
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Approval Notes (Optional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->approveByAdmin();
                    $this->record->update(['approval_notes' => $data['approval_notes'] ?? null]);
                    Notification::make()
                        ->title('✓ Report approved')
                        ->body('This streamer log entry has been approved.')
                        ->success()
                        ->send();
                    $this->refresh();
                });

            $actions[] = Action::make('reject_report')
                ->label('Reject & Return')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isSubmitted() && $this->record->approval_status !== 'approved')
                ->form([
                    \Filament\Forms\Components\Textarea::make('approval_notes')
                        ->label('Rejection Reason (Required)')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->rejectByAdmin($data['approval_notes']);
                    Notification::make()
                        ->title('Report rejected')
                        ->body('Report returned to streamer for revisions.')
                        ->warning()
                        ->send();
                    $this->refresh();
                });

            $actions[] = Action::make('reopen_for_edit')
                ->label('Reopen for Editing')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn () => $this->record->isLocked() && $this->record->approval_status === 'pending_approval')
                ->requiresConfirmation()
                ->modalHeading('Reopen for Editing')
                ->modalDescription('This will allow the streamer to make changes again.')
                ->action(function () {
                    $this->record->unlockReport();
                    $this->record->update(['submitted_at' => now()]);
                    Notification::make()
                        ->title('Report unlocked')
                        ->body('Streamer can now edit this report.')
                        ->info()
                        ->send();
                    $this->refresh();
                });
        }

        return $actions;
    }

    public function getSubheading(): ?string
    {
        /** @var StreamerLogEntry $record */
        $record = $this->record;
        $isStreamer = auth()->user()?->isStreamer() && !auth()->user()?->isAdmin();

        if ($isStreamer) {
            if (StreamerLogResource::isLockedForCurrentUser($record)) {
                return '🔒 View-only. Use the "End of Stream" form to log new items, or request edit permission if you need to make changes.';
            }
            return '📋 Your show summary. Use the "End of Stream" form to add items sold.';
        }

        if ($record->status === 'pending') {
            $total = $record->show?->orders()->count() ?? 0;
            $mapped = $record->show?->orders()->whereNotNull('inventory_item_id')->count() ?? 0;

            $mappingProgress = $total > 0
                ? "{$mapped}/{$total} items mapped"
                : 'No items to map';

            return "Streamer entered {$total} item(s). Review and approve.";
        }

        if ($record->status === 'streamer_reviewed') {
            return 'Awaiting admin review and approval of this streamer log entry.';
        }

        if ($record->status === 'admin_approved' && $record->needsFulfillmentReview()) {
            return 'Pending fulfillment team review of PWE/label counts.';
        }

        return null;
    }

    public function getHeadingHtml(): string
    {
        /** @var StreamerLogEntry $record */
        $record = $this->record;
        $show = $record->show;

        $progressData = match($record->status) {
            'pending' => [
                'current' => 1,
                'total' => 3,
                'steps' => ['Map Items', 'Fill Costs', 'Review'],
            ],
            'streamer_reviewed' => [
                'current' => 2,
                'total' => 3,
                'steps' => ['Map Items', 'Admin Review', 'Complete'],
            ],
            'admin_approved' => [
                'current' => 3,
                'total' => 3,
                'steps' => ['Map Items', 'Admin Review', 'Complete'],
            ],
            default => [
                'current' => 1,
                'total' => 3,
                'steps' => ['Map Items', 'Admin Review', 'Complete'],
            ],
        };

        $progressHtml = view('components.workflow-progress', $progressData)->render();

        return <<<HTML
            <div>
                <h1 class="text-3xl font-bold">{{ $show?->title ?? 'Streamer Log' }}</h1>
                <div class="mt-4">
                    {$progressHtml}
                </div>
            </div>
        HTML;
    }

    protected function afterSave(): void
    {
        /** @var StreamerLogEntry $record */
        $record = $this->record;

        // Auto-fill gross_revenue from the show if it wasn't set.
        if (! $record->gross_revenue && $record->show) {
            $record->gross_revenue = $record->show->gross_revenue;
        }

        // Recalculate profit share once we have cost data.
        if ($record->gross_revenue && $record->product_cost !== null) {
            $record->profit_share_amount = $record->profitShareAmount();
        }
        $record->save();

        // Auto-create a shipping surcharge when packages over $500 are logged.
        if (($record->number_of_packages_over_500 ?? 0) > 0 && $record->show && $record->streamer) {
            app(ShippingSurchargeService::class)->createForShow(
                $record->show,
                $record->streamer,
                $record->number_of_packages_over_500,
                "Auto from streamer log #{$record->id}",
            );
        }
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [
            route('filament.admin.resources.streamer-logs.index') => 'Streamer Logs',
        ];

        if ($this->record->show) {
            $breadcrumbs[null] = $this->record->show->title ?: 'Untitled Show';
        }

        return $breadcrumbs;
    }

    public function removeItem(int $orderId): void
    {
        $order = \App\Models\WhatnotShowOrder::find($orderId);
        if ($order && $order->show_id === $this->record->show_id) {
            $order->delete();
            Notification::make()
                ->title('Item removed')
                ->body('The item has been removed from this show.')
                ->info()
                ->send();
            $this->refresh();
        }
    }
}
