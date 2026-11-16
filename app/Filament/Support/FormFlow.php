<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard\Step;

/**
 * One description of a long form, shown two ways: as steps when something is
 * being created (one short screen at a time, each checked before the next) and
 * as tabs when it is being edited (jump straight to Pay, Login, ...). Both read
 * the same parts, so the wizard and the tabs cannot drift apart.
 *
 * A part: ['key' => 'pay', 'label' => 'Pay', 'icon' => 'heroicon-o-banknotes',
 *          'intro' => 'One line on what this part is for.', 'schema' => [...],
 *          'visible' => bool|Closure, 'columns' => int]
 */
final class FormFlow
{
    /** @param list<array<string, mixed>> $parts */
    public static function tabs(string $id, array $parts): Tabs
    {
        return Tabs::make($id)
            ->tabs(array_map(fn (array $p) => Tab::make($p['label'])
                ->icon($p['icon'] ?? null)
                ->visible($p['visible'] ?? true)
                ->columns($p['columns'] ?? 1)
                ->schema(self::body($p)), $parts))
            ->persistTabInQueryString($id.'-tab')
            ->columnSpanFull()
            ->extraAttributes(['class' => 'vx-form-tabs']);
    }

    /** @param list<array<string, mixed>> $parts @return list<Step> */
    public static function steps(array $parts): array
    {
        return array_map(fn (array $p) => Step::make($p['label'])
            ->icon($p['icon'] ?? null)
            ->description($p['step'] ?? null)
            ->visible($p['visible'] ?? true)
            ->columns($p['columns'] ?? 1)
            ->schema(self::body($p)), $parts);
    }

    private static function body(array $p): array
    {
        $intro = isset($p['intro'])
            ? [Text::make($p['intro'])->color('gray')->columnSpanFull()->extraAttributes(['class' => 'vx-form-intro'])]
            : [];

        return [...$intro, ...$p['schema']];
    }
}
