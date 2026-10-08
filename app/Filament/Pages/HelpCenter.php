<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;

class HelpCenter extends Page
{
    protected static ?string $title = 'Help & Guides';
    protected static ?string $slug = 'help-guides';
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Help'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-question-mark-circle'; }
    public static function canAccess(): bool { return auth()->check(); }
    public function getView(): string { return 'filament.pages.help-center'; }
}
