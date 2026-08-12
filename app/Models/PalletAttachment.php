<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PalletAttachment extends Model
{
    use SoftDeletes;

    const TYPE_PHOTO = 'photo';
    const TYPE_DOCUMENT = 'document';
    const TYPE_SIGNATURE = 'signature';
    const TYPE_RECEIPT = 'receipt';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'pallet_id',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'description',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PHOTO => 'Photo',
            self::TYPE_DOCUMENT => 'Document',
            self::TYPE_SIGNATURE => 'Signature',
            self::TYPE_RECEIPT => 'Receipt',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public function getFileUrl(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
