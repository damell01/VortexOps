<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'scan_session_id',
        'barcode',
        'inventory_item_id',
        'quantity',
        'scanned_at',
        'result', // 'success', 'not_found', 'duplicate', 'error'
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(ScanSession::class, 'scan_session_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
