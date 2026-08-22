<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamerLogEntry extends Model
{
    protected $fillable = [
        'show_id', 'streamer_id', 'status', 'hard_copy', 'hours_streamed',
        'number_of_shipments', 'pwe_count', 'label_count', 'number_of_packages_over_500',
        'pwe_pay', 'hourly_pay', 'profit_share_amount', 'profit_share_paid', 'tips_paid',
        'total_due', 'total_paid', 'business_net_rev', 'gross_revenue', 'product_cost',
        'reviewed_by', 'reviewed_at', 'streamer_reviewed_at', 'fulfillment_reviewed_by',
        'fulfillment_reviewed_at', 'notes', 'draft_step', 'draft_saved_at', 'submitted_at', 'locked_at', 'edit_window_minutes',
        'approval_requested_at', 'approval_status', 'approval_notes',
    ];

    protected $casts = [
        'hard_copy' => 'boolean',
        'profit_share_paid' => 'boolean',
        'hours_streamed' => 'decimal:2',
        'pwe_pay' => 'decimal:2',
        'hourly_pay' => 'decimal:2',
        'profit_share_amount' => 'decimal:2',
        'tips_paid' => 'decimal:2',
        'total_due' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'business_net_rev' => 'decimal:2',
        'gross_revenue' => 'decimal:2',
        'product_cost' => 'decimal:2',
        'number_of_shipments' => 'integer',
        'pwe_count' => 'integer',
        'label_count' => 'integer',
        'number_of_packages_over_500' => 'integer',
        'edit_window_minutes' => 'integer',
        'draft_step' => 'integer',
        'draft_saved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'streamer_reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'fulfillment_reviewed_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            'pending' => 'Pending',
            'changes_requested' => 'Changes Requested',
            'streamer_reviewed' => 'Streamer Reviewed',
            'admin_approved' => 'Admin Approved',
        ];
    }

    public function profitShareAmount(): float
    {
        $gross = (float) ($this->gross_revenue ?? $this->show?->gross_revenue ?? 0);
        $cost = (float) ($this->product_cost ?? 0);
        $psPct = (float) ($this->streamer?->payout_percentage ?? 0);
        return round(max(0, $gross - $cost) * ($psPct / 100), 2);
    }

    public function show(): BelongsTo { return $this->belongsTo(Show::class); }

    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) return $query;
        return $query->whereHas('show', fn (\Illuminate\Database\Eloquent\Builder $q) =>
            $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
        );
    }

    public function items(): HasMany { return $this->hasMany(StreamerLogItem::class, 'streamer_log_entry_id'); }
    public function showOrders(): HasMany { return $this->hasMany(WhatnotShowOrder::class, 'show_id', 'show_id'); }
    public function streamer(): BelongsTo { return $this->belongsTo(Streamer::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function fulfillmentReviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'fulfillment_reviewed_by'); }

    public function needsFulfillmentReview(): bool
    {
        return $this->status === 'admin_approved'
            && $this->fulfillment_reviewed_at === null
            && ($this->streamer?->payout_type === 'pwe_labels');
    }

    public function isSubmitted(): bool { return $this->submitted_at !== null; }
    public function isLocked(): bool { return $this->locked_at !== null; }

    public function canStreamerEdit(): bool
    {
        if ($this->status === 'admin_approved' || $this->approval_status === 'approved') return false;
        if (! $this->isSubmitted()) return true;
        if ($this->isLocked()) return false;
        if (! $this->submitted_at) return true;
        return ! $this->submitted_at->addMinutes($this->edit_window_minutes)->isPast();
    }

    public function getEditWindowExpiresAt(): ?\Illuminate\Support\Carbon
    {
        return $this->submitted_at ? $this->submitted_at->addMinutes($this->edit_window_minutes) : null;
    }

    public function getMinutesUntilEditWindowCloses(): ?int
    {
        if (! $this->submitted_at) return null;
        return max(0, now()->diffInMinutes($this->getEditWindowExpiresAt(), false));
    }

    public function submitReport(): array
    {
        $this->update([
            'submitted_at' => now(),
            'streamer_reviewed_at' => now(),
            'status' => 'streamer_reviewed',
            'approval_status' => 'pending_approval',
            'approval_requested_at' => now(),
            'locked_at' => null,
            'edit_window_minutes' => $this->edit_window_minutes ?? 120,
        ]);

        $postingPolicy = (string) Setting::get('show_inventory_posting_policy', 'on_submit');
        $problems = $this->inventoryPostingProblems();

        if ($postingPolicy === 'on_submit') {
            $problems = $this->postInventoryMovements();
        } elseif ($postingPolicy === 'clean_only' && $problems === []) {
            $problems = $this->postInventoryMovements();
        }

        $this->applyReviewPolicy($problems);
        return $problems;
    }

    private function applyReviewPolicy(array $problems): void
    {
        $reviewPolicy = (string) Setting::get('show_report_review_policy', 'required');
        if ($reviewPolicy === 'required') return;
        if ($reviewPolicy === 'exceptions_only' && $problems !== []) return;
        $this->approveAutomatically($reviewPolicy === 'auto' ? 'Auto-approved by workflow policy.' : 'Auto-approved: no inventory exceptions.');
    }

    public function approveAutomatically(?string $note = null): array
    {
        $postingProblems = [];
        if ((string) Setting::get('show_inventory_posting_policy', 'on_submit') === 'on_approval') {
            $postingProblems = $this->postInventoryMovements();
        }

        $combinedNote = trim(implode("\n", array_filter([
            $this->approval_notes,
            $note,
            $postingProblems ? 'Inventory exceptions: ' . implode(' | ', $postingProblems) : null,
        ])));

        $this->update([
            'status' => 'admin_approved',
            'approval_status' => 'approved',
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'locked_at' => now(),
            'approval_notes' => $combinedNote ?: null,
        ]);

        return $postingProblems;
    }

    public function reconcileWorkflowAfterFix(): array
    {
        $problems = $this->inventoryPostingProblems();
        $postingPolicy = (string) Setting::get('show_inventory_posting_policy', 'on_submit');

        if ($postingPolicy === 'clean_only' && $problems === []) {
            $problems = $this->postInventoryMovements();
        }

        if ($this->status !== 'admin_approved') {
            $this->applyReviewPolicy($problems);
        }

        return $problems;
    }

    public function lockReport(): void { $this->update(['locked_at' => now()]); }
    public function unlockReport(): void { $this->update(['locked_at' => null]); }

    public function requestAdminApproval(string $notes = ''): void
    {
        $this->update([
            'approval_requested_at' => now(),
            'approval_status' => 'pending_approval',
            'approval_notes' => $notes,
        ]);
    }

    public function approveByAdmin(): array
    {
        $postingProblems = [];
        if ((string) Setting::get('show_inventory_posting_policy', 'on_submit') === 'on_approval') {
            $postingProblems = $this->postInventoryMovements();
        }

        $this->update([
            'status' => 'admin_approved',
            'approval_status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'locked_at' => now(),
            'approval_notes' => $postingProblems === []
                ? $this->approval_notes
                : trim(($this->approval_notes ? $this->approval_notes . "\n" : '') . 'Inventory exceptions: ' . implode(' | ', $postingProblems)),
        ]);

        $this->notifyStreamer('✓ Show Report Approved', "Your show report for {$this->show?->title} has been approved.", 'success');
        return $postingProblems;
    }

    public function rejectByAdmin(string $notes = ''): void
    {
        $this->update([
            'status' => 'streamer_reviewed',
            'approval_status' => 'rejected',
            'approval_notes' => $notes,
            'locked_at' => null,
        ]);

        $this->restoreInventoryOnRejection();
        $this->notifyStreamer('Changes Requested on Your Show Report', "Your show report for {$this->show?->title} needs revision.\n\nReason: {$notes}", 'warning');
    }

    private function notifyStreamer(string $title, string $body, string $tone): void
    {
        $user = $this->streamer?->user;
        if (! $user) return;
        $notification = \Filament\Notifications\Notification::make()->title($title)->body($body);
        $notification = $tone === 'success' ? $notification->success() : $notification->warning();
        $notification->sendToDatabase($user);
    }

    public function inventoryPostingProblems(): array
    {
        if (! $this->show) return ['No show attached to this report.'];
        if (! $this->streamer) return ['No streamer attached to this report.'];

        $problems = [];
        $locationIds = $this->streamer->inventoryLocations()->pluck('inventory_locations.id');

        foreach ($this->items()->with('inventoryItem')->get() as $line) {
            if (! $line->inventoryItem) {
                $problems[] = "\"{$line->item_name}\" is not linked to an inventory product.";
                continue;
            }

            $quantity = max(0, (int) $line->quantity - max(0, (int) $line->deducted_quantity));
            if ($quantity === 0) continue;

            $onHand = InventoryStock::where('inventory_item_id', $line->inventory_item_id)
                ->whereIn('inventory_location_id', $locationIds)
                ->sum('quantity');

            if ((float) $onHand < $quantity) {
                $problems[] = "\"{$line->item_name}\" needs {$quantity} more but only " . (float) $onHand . ' is in streamer inventory.';
            }
        }

        return $problems;
    }

    public function postInventoryMovements(): array
    {
        $problems = [];
        if (! $this->show) return ['No show attached to this report.'];
        if (! $this->streamer) return ['No streamer attached to this report.'];

        $inventoryService = app(\App\Services\InventoryService::class);
        $locations = $this->streamer->inventoryLocations()->get();

        foreach ($this->items()->with('inventoryItem')->get() as $line) {
            $alreadyPosted = max(0, (int) $line->deducted_quantity);
            $requested = max(0, (int) $line->quantity);
            $quantity = max(0, $requested - $alreadyPosted);
            if ($quantity === 0) continue;

            if (! $line->inventoryItem) {
                $problems[] = "\"{$line->item_name}\" is not linked to an inventory product, so no stock was posted.";
                continue;
            }

            $item = $line->inventoryItem;
            $candidates = $line->inventory_location_id
                ? $locations->where('id', $line->inventory_location_id)
                : $locations;

            $posted = false;
            foreach ($candidates as $location) {
                $stock = InventoryStock::where('inventory_item_id', $item->id)
                    ->where('inventory_location_id', $location->id)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->quantity < $quantity) continue;

                $movementType = match ($line->disposition ?? 'sold') {
                    'giveaway' => 'giveaway',
                    'promo' => 'promo',
                    'other' => 'show_other',
                    default => 'show_sale',
                };

                $inventoryService->adjustStock(
                    $item,
                    $location,
                    (float) $stock->quantity - $quantity,
                    $line->dispositionLabel() . " - show: {$this->show->title}",
                    $movementType,
                    'show',
                    $this->show->id,
                );

                $line->update([
                    'inventory_location_id' => $location->id,
                    'deducted_quantity' => $alreadyPosted + $quantity,
                ]);
                $posted = true;
                break;
            }

            if (! $posted) {
                $onHand = InventoryStock::where('inventory_item_id', $item->id)
                    ->whereIn('inventory_location_id', $locations->pluck('id'))
                    ->sum('quantity');
                $problems[] = "\"{$item->name}\" needed {$quantity} but only " . (float) $onHand . ' was available.';
            }
        }

        return $problems;
    }

    public function deductInventoryOnSubmission(): array
    {
        return $this->postInventoryMovements();
    }

    public function restoreInventoryOnRejection(): void
    {
        if (! $this->show || ! $this->streamer) return;

        $inventoryService = app(\App\Services\InventoryService::class);
        $locations = $this->streamer->inventoryLocations()->get();

        foreach ($this->items()->with('inventoryItem')->where('deducted_quantity', '>', 0)->get() as $line) {
            if (! $line->inventoryItem) continue;

            $item = $line->inventoryItem;
            $quantity = (int) $line->deducted_quantity;
            $candidates = $line->inventory_location_id ? $locations->where('id', $line->inventory_location_id) : $locations;

            foreach ($candidates as $location) {
                $stock = InventoryStock::where('inventory_item_id', $item->id)
                    ->where('inventory_location_id', $location->id)
                    ->first();
                if ($stock === null) continue;

                $inventoryService->adjustStock(
                    $item,
                    $location,
                    (float) $stock->quantity + $quantity,
                    "Show report reversed - {$this->show->title}",
                    'show_reversal',
                    'show',
                    $this->show->id,
                );
                $line->update(['deducted_quantity' => 0]);
                break;
            }
        }
    }
}
