<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ShowWorkflowControlWidget extends Widget
{
    protected static ?int $sort = -50;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-workflow-control';

    public string $postingPolicy = 'on_submit';
    public string $reviewPolicy = 'required';

    public function mount(): void
    {
        $this->postingPolicy = (string) Setting::get('show_inventory_posting_policy', 'on_submit');
        $this->reviewPolicy = (string) Setting::get('show_report_review_policy', 'required');
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function setPostingPolicy(string $policy): void
    {
        $this->authorizeAdmin();
        if (! in_array($policy, ['on_submit', 'clean_only', 'on_approval'], true)) return;

        Setting::set('show_inventory_posting_policy', $policy);
        $this->postingPolicy = $policy;

        Notification::make()->title('Inventory posting policy updated')->success()->send();
    }

    public function setReviewPolicy(string $policy): void
    {
        $this->authorizeAdmin();
        if (! in_array($policy, ['required', 'exceptions_only', 'auto'], true)) return;

        Setting::set('show_report_review_policy', $policy);
        $this->reviewPolicy = $policy;

        Notification::make()->title('Show review policy updated')->success()->send();
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        abort_unless(($user?->isAdmin() || $user?->isOwner()) ?? false, 403);
    }

    public function getCountsProperty(): array
    {
        $channelScope = fn ($q) => $q->whereHas('show', fn ($show) => $show->inChannelContext());

        $pendingReports = StreamerLogEntry::query()
            ->whereIn('status', ['pending', 'streamer_reviewed'])
            ->where($channelScope)
            ->count();

        $unmatchedLines = StreamerLogItem::query()
            ->whereNull('inventory_item_id')
            ->whereHas('logEntry.show', fn ($show) => $show->inChannelContext())
            ->count();

        $unassignedFulfillment = Show::query()
            ->inChannelContext()
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->whereHas('shipments')
            ->whereDoesntHave('fulfillmentUsers')
            ->count();

        $openShipments = \App\Models\Shipment::query()
            ->whereHas('show', fn ($show) => $show->inChannelContext())
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
            ->count();

        return [
            'pending_reports' => $pendingReports,
            'unmatched_lines' => $unmatchedLines,
            'unassigned_fulfillment' => $unassignedFulfillment,
            'open_shipments' => $openShipments,
        ];
    }
}
