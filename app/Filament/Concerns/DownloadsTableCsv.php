<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A "Download CSV" button on a list's table that exports exactly what the
 * list is showing: the current filters, search, tab and sort, and the columns
 * that are switched on, with values as they read on screen.
 *
 * Use on any Filament page with a table. Livewire runs `booted{Trait}` after
 * the table is built, so the button is added on top of whatever header
 * actions the resource declares. A table that already has its own CSV export
 * (an `export_csv` action) keeps that one instead of getting a second button.
 */
trait DownloadsTableCsv
{
    /** Livewire sends a download through the browser in one piece, so keep it bounded. */
    public const CSV_ROW_LIMIT = 20000;

    public function bootedDownloadsTableCsv(): void
    {
        $table = $this->getTable();

        if (! $table->hasQuery() || $table->getAction('export_csv') || $table->getAction('downloadCsv')) {
            return;
        }

        $table->pushHeaderActions([
            Action::make('downloadCsv')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Download the rows and columns shown here, with the current filters and search')
                ->action(fn (): StreamedResponse => $this->downloadTableCsv()),
        ]);
    }

    public function downloadTableCsv(): StreamedResponse
    {
        $columns = collect($this->getTable()->getVisibleColumns())
            ->reject(fn (Column $column) => $column instanceof ImageColumn)
            ->values();

        $query = $this->getFilteredSortedTableQuery()->limit(self::CSV_ROW_LIMIT);

        // Column states can reach into relations the table does not eager
        // load; on a one-off export that is fine, so let them load.
        $prevented = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        try {
            $rows = [];
            foreach ($query->get() as $record) {
                $rows[] = $columns->map(fn (Column $column) => $this->csvCell($column, $record))->all();
            }
        } finally {
            Model::preventLazyLoading($prevented);
        }

        $header = $columns->map(fn (Column $column) => $this->csvText($column->getLabel()) ?: Str::headline($column->getName()))->all();
        $filename = Str::slug(strip_tags((string) $this->getTitle()) ?: 'export').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads accents and emoji as UTF-8
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(Column $column, Model $record): string|int|float
    {
        try {
            $column->record($record);
            $state = $column->getState();

            if (is_iterable($state)) {
                $state = collect($state)->map(fn ($s) => $this->csvValue($column, $s))->filter(fn ($s) => $s !== '')->implode(', ');
            } else {
                $state = $this->csvValue($column, $state);
            }
        } catch (\Throwable) {
            return '';
        }

        return $state;
    }

    private function csvValue(Column $column, mixed $state): string|int|float
    {
        if ($state === null || $state === '') {
            return '';
        }

        if (is_bool($state)) {
            return $state ? 'Yes' : 'No';
        }
        if ($state instanceof \BackedEnum && method_exists($state, 'getLabel')) {
            return $this->csvText($state->getLabel());
        }
        if ($state instanceof \UnitEnum) {
            return $state instanceof \BackedEnum ? (string) $state->value : $state->name;
        }

        // As it reads on screen: money with its symbol, dates in the list's format.
        $text = $column instanceof TextColumn ? $column->formatState($state) : $state;
        if (is_int($text) || is_float($text)) {
            return $text;
        }
        $text = $this->csvText($text);

        // A cell starting with = + - @ would run as a formula in Excel.
        if ($text !== '' && ! is_numeric($text) && in_array($text[0], ['=', '+', '-', '@'], true)) {
            $text = "'".$text;
        }

        return $text;
    }

    private function csvText(mixed $value): string
    {
        if ($value instanceof Htmlable) {
            $value = $value->toHtml();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        if (is_array($value) || is_object($value) && ! method_exists($value, '__toString')) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        $text = (string) $value;
        if (str_contains($text, '<')) {
            // Leave out decorative icons in badges: SVGs, and elements marked
            // aria-hidden or classed as an icon (Filament's HTML sanitiser can
            // drop aria-hidden, so the class is the reliable marker).
            $text = preg_replace([
                '#<svg\b.*?</svg>#s',
                '#<(\w+)\b[^>]*(?:aria-hidden="true"|class="[^"]*\bicon\b|class="[^"]*-icon\b)[^>]*>[^<]*</\1>#s',
            ], '', $text) ?? $text;
            $text = strip_tags($text);
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? '');
    }
}
