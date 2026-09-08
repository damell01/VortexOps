<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Product cost sheets used by operations have historically left A1 blank even
 * though column A is always the product name. Accept that real workbook shape
 * while keeping ProductSheetImporter's normal header matching for every other
 * field.
 */
class FlexibleProductSheetImporter extends ProductSheetImporter
{
    public function read(string $path, ?string $sheet = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);

        $worksheet = $sheet && $spreadsheet->getSheetByName($sheet)
            ? $spreadsheet->getSheetByName($sheet)
            : $spreadsheet->getSheet(0);

        if ($worksheet && trim((string) $worksheet->getCell('A1')->getValue()) === '') {
            // In the Streamer Log / Product cost ref sheet, column A is the
            // product-name column even though its header cell is intentionally
            // blank. Normalize only that one known omission.
            $worksheet->setCellValue('A1', 'PRODUCT NAME');
            IOFactory::createWriter($spreadsheet, $reader->getSpreadsheetVersion() ? 'Xlsx' : 'Xlsx');

            // Save with a writer selected from the original file extension so
            // XLSX/XLS/CSV uploads remain readable by the parent importer.
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $writerType = match ($extension) {
                'xls' => 'Xls',
                'csv', 'txt' => 'Csv',
                default => 'Xlsx',
            };
            IOFactory::createWriter($spreadsheet, $writerType)->save($path);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return parent::read($path, $sheet);
    }
}
