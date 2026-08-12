<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentPackage extends Model
{
    protected $fillable = [
        'tracking_number',
        'carrier',
        'created_by',
        'shipped_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentPackageItem::class);
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped' && $this->shipped_at !== null;
    }

    public function getItemCount(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function getTotalValue(): float
    {
        return (float) $this->items()
            ->with('product')
            ->get()
            ->sum(fn ($item) => $item->quantity * ((float) $item->product->average_cost));
    }
}
