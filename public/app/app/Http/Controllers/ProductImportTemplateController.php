<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductImportTemplateController extends Controller
{
    public function __invoke()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');

        // Headers
        $headers = ['Name', 'Batch Number', 'Expiry Date', 'Qty', 'Cost Price', 'Selling Price'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
        }

        // Header style
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Example rows
        $examples = [
            ['PARACETAMOL 500MG TAB',     'B12345',  '08/2026', 100, 50.00,   ''],
            ['AMOXICILLIN 500MG CAPS',     'A40725',  '04/2027',  50, 250.00,  ''],
            ['VITAMIN C 500MG TAB',        'V10011',  '12/2027', 200, 80.00,   ''],
        ];
        foreach ($examples as $i => $row) {
            $rowNum = $i + 2;
            foreach ($row as $col => $value) {
                $sheet->setCellValue(chr(65 + $col) . $rowNum, $value);
            }
        }

        // Notes row
        $sheet->setCellValue('A5', '← Required');
        $sheet->setCellValue('B5', '← Optional (auto-generated if blank)');
        $sheet->setCellValue('C5', '← MM/YYYY format e.g. 08/2026');
        $sheet->setCellValue('D5', '← Required');
        $sheet->setCellValue('E5', '← Required (₦)');
        $sheet->setCellValue('F5', '← Optional (auto-calculated from cost × 1.4 if blank)');
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '6b7280']],
        ]);

        // Column widths
        $widths = [40, 20, 16, 8, 14, 30];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension(chr(65 + $col))->setWidth($width);
        }

        // Format expiry column as text to prevent Excel auto-converting dates
        $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode('@');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'products-import-template.xlsx', [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="products-import-template.xlsx"',
        ]);
    }
}
