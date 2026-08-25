<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Concerns\RespectsRoleVisibility;
use App\Support\InventoryManual;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * The handbook, on screen and clickable.
 *
 * The same material as the printed one, which is deliberate: two documents
 * saying nearly the same thing is how a manual goes stale, because only one
 * of them ever gets corrected. Both read from InventoryManual, so a fix to a
 * sentence lands in the PDF and on this page at once.
 *
 * Built around a module key rather than hardcoding Inventory. Only Inventory
 * has content today; adding Shows or Payouts later is a matter of another
 * class with the same shape and a row in modules(), not a second page.
 */
class Handbook extends Page
{
    // Opening it answers to Roles & Permissions, and so does the link to it —
    // a sidebar entry that survives being switched off is the same bug as a
    // page that opens when it should not.
    use HasAdminNavVisibility;
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Handbook';
    protected static ?int $navigationSort = 90;

    /**
     * The two back-of-the-book pages, addressed the same way a section is so
     * the sidebar, the URL and openSection() do not each need a special case.
     */
    public const TROUBLESHOOTING = -1;
    public const SCREEN_INDEX = -2;

    /** Which module's handbook is open. */
    public string $module = 'inventory';

    /** Which section within it, by index. Null means the overview. */
    public ?int $section = null;

    /** Free-text filter across every step in the open module. */
    public string $search = '';

    public function getTitle(): string
    {
        return 'Handbook';
    }

    public function getSubheading(): ?string
    {
        return 'How the app works, section by section. Same material as the printed handbook.';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public function getView(): string
    {
        return 'filament.pages.handbook';
    }

    /**
     * Deep-linkable, so "read step 4 of Receiving" can be sent to someone as a
     * link rather than as directions to a link.
     */
    protected function queryString(): array
    {
        return [
            'module'  => ['except' => 'inventory'],
            'section' => ['except' => null],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('export.inventory-manual-pdf'))
                ->openUrlInNewTab()
                ->visible(fn () => $this->module === 'inventory'),
        ];
    }

    /**
     * The modules that have a handbook.
     *
     * @return array<string, array{label: string, icon: string, source: class-string, ready: bool}>
     */
    public function modules(): array
    {
        return [
            'inventory' => [
                'label'  => 'Inventory',
                'icon'   => 'heroicon-o-cube',
                'source' => InventoryManual::class,
                'ready'  => true,
            ],
            'shows' => [
                'label'  => 'Shows & Streams',
                'icon'   => 'heroicon-o-video-camera',
                'source' => null,
                'ready'  => false,
            ],
            'payouts' => [
                'label'  => 'Payouts',
                'icon'   => 'heroicon-o-banknotes',
                'source' => null,
                'ready'  => false,
            ],
            'receiving' => [
                'label'  => 'Fulfillment',
                'icon'   => 'heroicon-o-truck',
                'source' => null,
                'ready'  => false,
            ],
        ];
    }

    public function selectModule(string $module): void
    {
        if (($this->modules()[$module]['ready'] ?? false) === true) {
            $this->module  = $module;
            $this->section = null;
            $this->search  = '';
        }
    }

    public function openSection(?int $index): void
    {
        $this->section = $index;
        $this->search  = '';
    }

    /** @return array<int, array<string, mixed>> */
    public function allSections(): array
    {
        $source = $this->modules()[$this->module]['source'] ?? null;

        return $source ? $source::sections() : [];
    }

    /**
     * What the main pane shows: one section, or everything a search matched.
     *
     * @return array<int, array{index: int, section: array<string, mixed>}>
     */
    public function visibleSections(): array
    {
        $sections = $this->allSections();
        $needle   = trim(mb_strtolower($this->search));

        if ($needle !== '') {
            $matched = [];

            foreach ($sections as $i => $section) {
                $steps = array_values(array_filter(
                    $section['steps'],
                    fn (array $step) => str_contains(mb_strtolower($this->haystackFor($step)), $needle),
                ));

                if ($steps !== []) {
                    $matched[] = ['index' => $i, 'section' => ['title' => $section['title'], 'blurb' => $section['blurb'], 'steps' => $steps]];
                }
            }

            return $matched;
        }

        if ($this->section !== null && isset($sections[$this->section])) {
            return [['index' => $this->section, 'section' => $sections[$this->section]]];
        }

        return [];
    }

    /** Everything in a step a search should look at, including its fields. */
    private function haystackFor(array $step): string
    {
        $parts = [$step['title'], $step['where'] ?? '', $step['note'] ?? ''];
        $parts = array_merge($parts, $step['body']);

        foreach ($step['fields'] ?? [] as [$field, $meaning]) {
            $parts[] = $field . ' ' . $meaning;
        }

        return implode(' ', $parts);
    }

    public function getSearchResultCountProperty(): int
    {
        return array_sum(array_map(
            fn ($entry) => count($entry['section']['steps']),
            $this->visibleSections(),
        ));
    }

    public function getTotalStepsProperty(): int
    {
        return array_sum(array_map(fn ($s) => count($s['steps']), $this->allSections()));
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function troubleshooting(): array
    {
        $source = $this->modules()[$this->module]['source'] ?? null;

        return $source ? $source::troubleshooting() : [];
    }

    /** @return array<int, array{0: string, 1: string}> */
    public function screenIndex(): array
    {
        $source = $this->modules()[$this->module]['source'] ?? null;

        return $source ? $source::screenIndex() : [];
    }

    public function imageUrl(string $shot): string
    {
        return asset(InventoryManual::IMAGE_DIR . '/' . $shot);
    }

    /** Is one of the two back-of-the-book pages open? */
    public function onExtraPage(): bool
    {
        return in_array($this->section, [self::TROUBLESHOOTING, self::SCREEN_INDEX], true);
    }

    /**
     * What comes after the open section, as [index, title], so a reader can
     * work forwards without going back to the contents each time.
     *
     * @return array{0: int, 1: string}|null
     */
    public function neighbour(int $direction): ?array
    {
        if ($this->section === null || $this->search !== '') {
            return null;
        }

        $sections = $this->allSections();
        $next     = $this->section + $direction;

        if (isset($sections[$next])) {
            return [$next, $sections[$next]['title']];
        }

        // Past the last section come troubleshooting and the screen index —
        // they are part of the read-through, not an appendix nobody reaches.
        if ($direction > 0 && $this->section === count($sections) - 1) {
            return [self::TROUBLESHOOTING, 'When something looks wrong'];
        }

        if ($direction > 0 && $this->section === self::TROUBLESHOOTING) {
            return [self::SCREEN_INDEX, 'Every screen, and what it is for'];
        }

        if ($direction < 0 && $this->section === self::TROUBLESHOOTING) {
            return [count($sections) - 1, $sections[count($sections) - 1]['title']];
        }

        if ($direction < 0 && $this->section === self::SCREEN_INDEX) {
            return [self::TROUBLESHOOTING, 'When something looks wrong'];
        }

        return null;
    }
}
