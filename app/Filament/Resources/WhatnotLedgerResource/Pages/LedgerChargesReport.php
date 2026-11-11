<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use Filament\Resources\Pages\Page;

/**
 * Ledger charges (chargebacks) for everyone charged for them: Estill
 * Distribution streamers' shipping charges, refunds and order refund
 * shipping charges. Streamers see their own on My Charges.
 */
class LedgerChargesReport extends Page
{
    protected static string $resource = WhatnotLedgerResource::class;

    protected static ?string $title = 'Ledger charges';

    public function getView(): string
    {
        return 'filament.resources.whatnot-ledger-resource.pages.ledger-charges';
    }

    public function getSubheading(): ?string
    {
        return 'What Estill Distribution streamers are charged: shipping charges (their own type) and chargebacks & corrections (refunds and adjustments).';
    }
}
