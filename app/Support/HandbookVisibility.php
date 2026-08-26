<?php

namespace App\Support;

/**
 * The handbook shows you the app you have, not the app someone else has.
 *
 * Every step names the screen it walks through, and a step whose screen this
 * person cannot open is worse than useless: it is instructions for a button
 * that is not there, which reads as "this app is broken for me" rather than
 * "you do not do that job". The same goes for the screen index.
 *
 * Access is asked of the screen itself — canAccess() on the Filament page or
 * resource — so the handbook cannot disagree with the sidebar. A module
 * switched off, a role restricted on Roles & Permissions, and a page an admin
 * only ever sees all come out right without this class knowing why.
 */
class HandbookVisibility
{
    /**
     * Sections with unreachable steps removed, and sections left empty dropped.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public static function sections(array $sections): array
    {
        $visible = [];

        foreach ($sections as $section) {
            $steps = array_values(array_filter(
                $section['steps'],
                fn (array $step) => static::canSee($step['screen'] ?? null),
            ));

            if ($steps === []) {
                continue;
            }

            $visible[] = ['steps' => $steps] + $section;
        }

        return $visible;
    }

    /**
     * The screen index, and the troubleshooting page, share a shape: label,
     * text, and optionally the screen it is about. A symptom with no screen
     * named — "a scan is slow" — is advice that applies wherever you are, and
     * is always shown.
     *
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $rows
     * @return array<int, array{0: string, 1: string, 2?: string}>
     */
    public static function rows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row) => static::canSee($row[2] ?? null),
        ));
    }

    /** @param array<int, array{0: string, 1: string, 2?: string}> $rows */
    public static function screens(array $rows): array
    {
        return static::rows($rows);
    }

    /** @param array<int, array{0: string, 1: string, 2?: string}> $rows */
    public static function troubleshooting(array $rows): array
    {
        return static::rows($rows);
    }

    /**
     * Can this person open the screen a step describes?
     *
     * A step with no screen named is general advice and always shown. A class
     * that has gone missing — renamed, moved — is shown rather than hidden:
     * losing a page from the handbook silently is a worse failure than showing
     * one step too many, and the missing class will surface elsewhere.
     */
    protected static function canSee(null|string|array $screen): bool
    {
        if ($screen === null) {
            return true;
        }

        foreach ((array) $screen as $class) {
            if (! is_string($class) || ! class_exists($class) || ! method_exists($class, 'canAccess')) {
                return true;
            }

            try {
                if ($class::canAccess()) {
                    return true;
                }
            } catch (\Throwable) {
                // A screen that cannot answer the question is not evidence
                // that this person may not use it.
                return true;
            }
        }

        return false;
    }
}
