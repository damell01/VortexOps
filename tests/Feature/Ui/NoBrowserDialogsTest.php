<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The panel talks through Filament, not through the browser's own dialogs.
 *
 * alert() and prompt() block the page, cannot be styled, look nothing like
 * the rest of the panel, and are suppressed outright in some embedded
 * contexts. Two of the ones removed were not cosmetic at all:
 *
 *  - `onclick="return confirm(...)"` next to a wire:click. Livewire binds its
 *    own listener, so returning false stops nothing — pressing Cancel on the
 *    full-resync warning started a full resync.
 *  - confirm() inside a beforeunload handler. Browsers ignore dialogs raised
 *    there and show their own prompt for preventDefault()/returnValue, so the
 *    call could never display; and an ignored confirm() returns undefined, so
 *    `!confirm(...)` was always true and the guard fired regardless.
 *
 * Filament actions have requiresConfirmation() and modal forms; JS has
 * FilamentNotification. Neither needs a native dialog.
 */
class NoBrowserDialogsTest extends TestCase
{
    /** @return array<int, string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach ([resource_path('views'), resource_path('js')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_no_view_or_script_raises_a_native_dialog(): void
    {
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            foreach (file($path) as $index => $line) {
                // Blade comments are where the removals are explained.
                if (str_contains($line, '{{--') || preg_match('#^\s*(//|\*)#', $line)) {
                    continue;
                }

                // A leading dot or word character means it is someone's own
                // method — Notification::alert(), $this->confirm() and so on.
                if (preg_match('/(^|[^.\w$>])(alert|prompt)\s*\(/', $line)
                    || preg_match('/(^|[^.\w$>-])confirm\s*\(/', $line)) {
                    $offenders[] = str_replace(base_path() . '/', '', $path) . ':' . ($index + 1) . ' — ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Use a Filament action with requiresConfirmation(), a modal form, or'],
            ['FilamentNotification instead of a browser dialog:'],
            $offenders
        )));
    }

    public function test_no_view_mixes_inline_and_block_php_directives(): void
    {
        // Blade collects @php(...) and @php ... @endphp in the same raw-block
        // pass, and an inline one above a block one pairs with the block's
        // @endphp. The boundary moves, and the file stops compiling somewhere
        // else entirely — the error names a directive that is not in the file.
        //
        // It has cost this codebase two production 500s. Either form alone is
        // fine; the two together in one file are the trap.
        $offenders = [];

        foreach ($this->sourceFiles() as $path) {
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $source = file_get_contents($path);

            $hasInline = (bool) preg_match('/@php\s*\(/', $source);
            $hasBlock  = str_contains($source, '@endphp');

            if ($hasInline && $hasBlock) {
                $offenders[] = str_replace(base_path() . '/', '', $path);
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These views mix @php(...) with @php ... @endphp. Blade pairs the inline'],
            ['one with the block\'s closing tag and the file stops compiling. Convert'],
            ['the inline ones to block form:'],
            $offenders
        )));
    }
}
