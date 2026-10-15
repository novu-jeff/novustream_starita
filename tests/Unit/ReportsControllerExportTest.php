<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportsController;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class ReportsControllerExportTest extends TestCase
{
    public function test_shared_header_uses_report_name_and_selected_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14'));
        $controller = new ReportsController();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Account Number', 'Name', 'Address']], null, 'A1');

        $method = new ReflectionMethod(ReportsController::class, 'addReportHeader');
        $method->setAccessible(true);
        $method->invoke($controller, $sheet, 'List of Active', '2026-10-01', '2026-10-31');

        $this->assertSame('STA RITA WATER DISTRICT', $sheet->getCell('A1')->getValue());
        $this->assertSame('List of Active', $sheet->getCell('A2')->getValue());
        $this->assertSame('FOR THE MONTH OF OCTOBER 2026', $sheet->getCell('A3')->getValue());
        $this->assertSame('Account Number', $sheet->getCell('A4')->getValue());

        $spreadsheet->disconnectWorksheets();
        Carbon::setTestNow();
    }

    public function test_existing_branded_header_is_updated_without_shifting_report_rows(): void
    {
        $controller = new ReportsController();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'STA RITA WATER DISTRICT');
        $sheet->setCellValue('A2', 'BILLING REPORT');
        $sheet->setCellValue('A3', 'FOR THE MONTH OF OCTOBER 2026');
        $sheet->fromArray([['Account No.', 'Name']], null, 'A4');
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('A3:B3');

        $method = new ReflectionMethod(ReportsController::class, 'addReportHeader');
        $method->setAccessible(true);
        $method->invoke($controller, $sheet, 'List of Active', '2026-10-01', '2026-10-31');

        $this->assertSame('List of Active', $sheet->getCell('A2')->getValue());
        $this->assertSame('FOR THE MONTH OF OCTOBER 2026', $sheet->getCell('A3')->getValue());
        $this->assertSame('Account No.', $sheet->getCell('A4')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_rows_are_written_in_chunks_at_the_requested_start_row(): void
    {
        $controller = new ReportsController();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        for ($index = 1; $index <= 1001; $index++) {
            $rows[] = ['Account Number' => 'A-' . $index, 'Name' => 'Customer ' . $index];
        }

        $method = new ReflectionMethod(ReportsController::class, 'writeRowsInChunks');
        $method->setAccessible(true);
        $method->invoke($controller, $sheet, $rows, 5, 500);

        $this->assertSame('A-1', $sheet->getCell('A5')->getValue());
        $this->assertSame('Customer 501', $sheet->getCell('B505')->getValue());
        $this->assertSame('A-1001', $sheet->getCell('A1005')->getValue());

        $spreadsheet->disconnectWorksheets();
    }
}