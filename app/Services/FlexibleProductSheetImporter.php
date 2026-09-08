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
            // The real Product cost ref sheet leaves A1 blank. Column A is
            // nevertheless the product-name column, so normalize that one
            // known omission before the normal importer validates headers.
            $worksheet->setCellValue('A1', 'PRODUCT NAME');

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
