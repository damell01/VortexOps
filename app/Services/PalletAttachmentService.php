<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\PalletAttachment;
use Illuminate\Support\Facades\Storage;

/**
 * Turning uploaded files into pallet attachments.
 *
 * Extracted from EditPallet so the pallet's own page can take uploads too.
 * Photographing a damaged case meant leaving the pallet, opening the edit
 * form, uploading, saving, and navigating back — which is not a thing anyone
 * does while holding a box.
 */
class PalletAttachmentService
{
    /** Mime types we recognise, and what kind of attachment each becomes. */
    private const TYPES = [
        'image/jpeg'      => PalletAttachment::TYPE_PHOTO,
        'image/png'       => PalletAttachment::TYPE_PHOTO,
        'image/webp'      => PalletAttachment::TYPE_PHOTO,
        'image/gif'       => PalletAttachment::TYPE_PHOTO,
        'application/pdf' => PalletAttachment::TYPE_DOCUMENT,
    ];

    /**
     * Record already-stored files against a pallet.
     *
     * @param  array<int, string|null>  $paths  paths relative to the public disk
     * @return int how many were attached
     */
    public function attach(Pallet $pallet, array $paths, ?string $description = null): int
    {
        $attached = 0;

        // Through the Storage facade rather than storage_path(): the previous
        // version built an absolute local path by hand, which ties this to the
        // local disk and cannot see a file on any other one.
        $disk = Storage::disk('public');

        foreach ($paths as $path) {
            if (! $path) {
                continue;
            }

            // An upload that did not land is skipped rather than recorded as a
            // broken row pointing at nothing.
            if (! $disk->exists($path)) {
                continue;
            }

            $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

            PalletAttachment::create([
                'pallet_id'   => $pallet->id,
                'type'        => self::TYPES[$mimeType] ?? PalletAttachment::TYPE_OTHER,
                'file_path'   => $path,
                'file_name'   => basename($path),
                'file_size'   => $disk->size($path),
                'mime_type'   => $mimeType,
                'description' => $description,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);

            $attached++;
        }

        if ($attached > 0) {
            $pallet->update(['attachments_count' => $pallet->attachments()->count()]);
        }

        return $attached;
    }
}
