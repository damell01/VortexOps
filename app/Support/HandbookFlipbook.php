<?php

namespace App\Support;

/**
 * A manual laid out as the pages of a book.
 *
 * The third way of reading the handbook, after the Handbook screen and the
 * printed PDF, and built from the same content class for the same reason they
 * are: three renderings of one text cannot drift, three texts can.
 *
 * This class only decides what goes on which page — the turning is the view's
 * business. Kept apart from the Blade so the page count, and which page a
 * section starts on, can be asserted without rendering anything.
 */
class HandbookFlipbook
{
    /** Rows of a back-of-the-book table that fit on one page. */
    public const TROUBLESHOOTING_PER_PAGE = 6;
    public const SCREENS_PER_PAGE = 9;

    /**
     * Every page of the book, in reading order, padded to an even count so the
     * last leaf has a back.
     *
     * @param  class-string  $source  A manual class: title(), subtitle(), sections(), troubleshooting(), screenIndex().
     * @return array<int, array<string, mixed>>
     */
    public static function pages(string $source): array
    {
        // Filtered exactly as the Handbook screen and the PDF are, so the book
        // describes the app this person has.
        $sections        = HandbookVisibility::sections($source::sections());
        $troubleshooting = HandbookVisibility::troubleshooting($source::troubleshooting());
        $screens         = HandbookVisibility::screens($source::screenIndex());

        $pages   = [];
        $pages[] = ['kind' => 'cover', 'title' => $source::title(), 'subtitle' => $source::subtitle()];
        $pages[] = ['kind' => 'contents', 'entries' => []];

        $entries = [];

        foreach ($sections as $s => $section) {
            $entries[] = ['label' => ($s + 1) . '. ' . $section['title'], 'page' => count($pages), 'steps' => count($section['steps'])];

            $pages[] = [
                'kind'    => 'section',
                'number'  => $s + 1,
                'title'   => $section['title'],
                'blurb'   => $section['blurb'],
                'icon'    => $section['icon'] ?? 'heroicon-o-book-open',
                'steps'   => array_column($section['steps'], 'title'),
            ];

            foreach ($section['steps'] as $n => $step) {
                $pages[] = [
                    'kind'    => 'step',
                    'section' => $section['title'],
                    'number'  => ($s + 1) . '.' . ($n + 1),
                    'step'    => $step,
                    'shots'   => array_values(array_filter(array_merge([$step['shot'] ?? null], $step['more'] ?? []))),
                ];
            }
        }

        if ($troubleshooting !== []) {
            $entries[] = ['label' => 'When something looks wrong', 'page' => count($pages), 'steps' => null];
            static::table($pages, 'troubleshooting', 'When something looks wrong', $troubleshooting, self::TROUBLESHOOTING_PER_PAGE);
        }

        if ($screens !== []) {
            $entries[] = ['label' => 'Every screen, and what it is for', 'page' => count($pages), 'steps' => null];
            static::table($pages, 'screens', 'Every screen, and what it is for', $screens, self::SCREENS_PER_PAGE);
        }

        $pages[1]['entries'] = $entries;

        // The back cover always lands on a left-hand page, which is where a
        // closed book shows it.
        if (count($pages) % 2 === 0) {
            $pages[] = ['kind' => 'blank'];
        }
        $pages[] = ['kind' => 'back', 'title' => $source::title()];

        return $pages;
    }

    /** @param  array<int, array<string, mixed>>  $pages */
    private static function table(array &$pages, string $kind, string $title, array $rows, int $perPage): void
    {
        $chunks = array_chunk($rows, $perPage);

        foreach ($chunks as $i => $chunk) {
            $pages[] = [
                'kind'  => $kind,
                'title' => $title,
                'part'  => count($chunks) > 1 ? ($i + 1) . ' of ' . count($chunks) : null,
                'rows'  => array_map(fn (array $row) => [$row[0], $row[1]], $chunk),
            ];
        }
    }
}
