<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Url;
use App\Support\GuidedHelp;

class HelpCenter extends Page
{
    protected static ?string $title = 'Help & Guides';
    protected static ?string $slug = 'help-guides';

    #[Url]
    public ?string $guide = null;

    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Help'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-question-mark-circle'; }
    public static function canAccess(): bool { return auth()->check(); }
    public function getView(): string { return 'filament.pages.help-center'; }

    public function selectGuide(string $guide): void
    {
        abort_unless(array_key_exists($guide, GuidedHelp::documents()), 403);
        $this->guide = $guide;
    }

    public function getViewData(): array
    {
        $documents = GuidedHelp::documents();
        $selectedGuide = isset($documents[$this->guide ?? '']) ? $this->guide : array_key_first($documents);
        return compact('documents', 'selectedGuide') + ['selectedDocument' => $documents[$selectedGuide ?? ''] ?? null];
    }
}
