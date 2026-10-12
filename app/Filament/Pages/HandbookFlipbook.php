<?php

namespace App\Filament\Pages;

use App\Support\HandbookFlipbook as Book;
use App\Support\InventoryManual;
use Filament\Pages\Page;

/**
 * The inventory handbook as a book you turn the pages of.
 *
 * A pilot: one module, reached from the Handbook screen rather than the
 * sidebar, so it can be tried without anyone losing the screen they know.
 * Whoever may open the Handbook may open this — it is the same text.
 */
class HandbookFlipbook extends Page
{
    protected static ?string $slug = 'handbook/flipbook';

    public function getTitle(): string { return InventoryManual::title(); }

    public static function canAccess(): bool { return Handbook::canAccess(); }

    public static function shouldRegisterNavigation(): bool { return false; }

    public function getView(): string { return 'filament.pages.handbook-flipbook'; }

    /** @return array<int, array<string, mixed>> */
    public function pages(): array
    {
        return Book::pages(InventoryManual::class);
    }

    public function imageUrl(string $shot): string
    {
        return asset(InventoryManual::IMAGE_DIR . '/' . $shot);
    }
}
