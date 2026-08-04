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

        return [
            // Streamers can request edit permission if locked
            Action::make('request_edit')
                ->label('Request Edit Permission')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => $isStreamer && StreamerLogResource::isLockedForCurrentUser($this->record))
                ->requiresConfirmation()
                ->modalHeading('Request Edit Permission')
                ->modalDescription('An admin will review your request and unlock this log entry for editing.')
                ->action(function () {
                    Notification::make()
                        ->title('Request sent')
                        ->body('An admin has been notified of your edit request.')
                        ->success()
                        ->send();
                    // TODO: Implement notification to admins about edit request
                }),
        ];
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
}
