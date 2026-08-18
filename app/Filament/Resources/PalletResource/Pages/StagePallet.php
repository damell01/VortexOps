<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\Pallet;
use Filament\Resources\Pages\Page;

/**
 * Retired. Staging is now worked on the pallet's own page.
 *
 * This was a second screen listing the same lines, with its own CSV import and
 * its own "ready to receive" button — so a pallet had two places showing two
 * versions of its manifest, and the one you landed on decided which actions you
 * could reach. Everything it did now lives on ViewPallet: Add Expected Item,
 * Import Manifest CSV, and Review Manifest, which is the checkpoint this
 * page's "Ready to Receive" button used to be.
 *
 * The route survives only so bookmarks and in-flight links land somewhere
 * useful instead of on a 404.
 */
class StagePallet extends Page
{
    protected static string $resource = PalletResource::class;

    public Pallet $record;

    public function getView(): string
    {
        return 'filament.pages.stage-pallet';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record;

        $this->redirect(PalletResource::getUrl('view', ['record' => $record]));
    }
}
