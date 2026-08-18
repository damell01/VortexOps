<?php

namespace Tests\Feature\Receiving;

use App\Models\Pallet;
use App\Models\Vendor;
use App\Services\PalletAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pallet photos must be written where they are read from.
 *
 * They were not. The app's default filesystem disk is `local`, and a Filament
 * FileUpload that does not name a disk uses the default — so uploads landed in
 * storage/app/private while this service looked on the `public` disk. exists()
 * was false for every one of them, so each upload was skipped and reported as
 * "Nothing was uploaded": no error, no file, no attachment row. The UI serves
 * attachments through the public symlink as well, so even a recorded one would
 * have 404'd.
 */
class PalletAttachmentDiskTest extends TestCase
{
    use RefreshDatabase;

    private function pallet(): Pallet
    {
        return Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'is_active' => true])->id,
            'status'    => 'receiving',
        ]);
    }

    private function jpeg(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');
    }

    public function test_the_upload_disk_is_named_rather_than_left_to_the_default(): void
    {
        // The whole bug in one line: relying on the default silently pointed
        // the uploads at a disk the service cannot see, and the default is not
        // something this feature gets to choose.
        $this->assertNotSame(
            config('filesystems.default'),
            PalletAttachmentService::DISK,
            'This test is only meaningful while the default disk differs; if they now match, the '
            .'coupling is still real and every upload field must keep naming the disk explicitly.'
        );
    }

    public function test_a_file_on_the_declared_disk_is_attached(): void
    {
        Storage::fake(PalletAttachmentService::DISK);

        $pallet = $this->pallet();
        Storage::disk(PalletAttachmentService::DISK)->put('pallets/photo.jpg', $this->jpeg());

        $attached = app(PalletAttachmentService::class)->attach($pallet, ['pallets/photo.jpg'], 'damage');

        $this->assertSame(1, $attached);
        $this->assertSame('damage', $pallet->attachments()->first()->description);
    }

    public function test_a_file_left_on_the_default_disk_is_not_attached(): void
    {
        // Exactly what an upload field without ->disk() produced. Asserted so
        // that if a field ever drops the disk again, the silence is a failing
        // test rather than photographs quietly going nowhere.
        Storage::fake(PalletAttachmentService::DISK);
        Storage::fake(config('filesystems.default'));

        $pallet = $this->pallet();
        Storage::disk(config('filesystems.default'))->put('pallets/photo.jpg', $this->jpeg());

        $attached = app(PalletAttachmentService::class)->attach($pallet, ['pallets/photo.jpg'], null);

        $this->assertSame(0, $attached);
        $this->assertSame(0, $pallet->attachments()->count());
    }

    public function test_every_pallet_upload_field_names_that_disk(): void
    {
        // Reads the actual configuration rather than trusting the source to
        // stay right: three fields across three files all have to agree with
        // the service, and nothing else enforces that.
        $files = [
            'app/Filament/Resources/PalletResource.php',
            'app/Filament/Resources/PalletResource/Pages/ViewPallet.php',
            'app/Filament/Resources/PalletResource/Pages/ReceivePallet.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));

            preg_match_all("/->directory\('pallets'\)/", $source, $uploads);

            $this->assertSame(
                count($uploads[0]),
                substr_count($source, 'PalletAttachmentService::DISK'),
                "{$file} has a pallet upload field that does not name the attachment disk."
            );
        }
    }
}
