<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletPackingSlip extends Model
{
    protected $fillable = [
        'pallet_id',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'extracted_text',
        'uploaded_by',
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
