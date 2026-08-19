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
    /**
     * The disk pallet files live on — write and read alike.
     *
     * Named here and referenced by every upload field rather than left to
     * each one, because the app's default disk is `local` and a FileUpload
     * that does not say otherwise uses it. That put the file in
     * storage/app/private while this looked on the public disk, so exists()
     * was false and every photo was dropped with "Nothing was uploaded".
     * getFileUrl() serves from the public symlink too, so anything recorded
     * against the local disk would 404 even if it had been saved.
     */
    public const DISK = 'public';

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
        $disk = Storage::disk(self::DISK);

        foreach ($paths as $path) {
            if (! $path) {
                continue;
            }

            // An upload that did not land is skipped rather than recorded as a
            // broken row pointing at nothing.
            if (! $disk->exists($path)) {
                continue;
            }

            // Photographed at the pallet on a phone, so the same several
            // megabytes of unseen pixels. Documents are left untouched.
            app(ImageCompressor::class)->compress(self::DISK, $path);

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
