<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use App\Models\Reading;
use App\Models\Bill;
use App\Models\ConcessionerAccount;
use Carbon\Carbon;
use DB;

class ReportsController extends Controller
{
    public function downloadFilesIndex()
    {
        $availableReports = [
            'Ageing (Detailed)',
            'Ageing (Summary)',
            'Ageing (Recap)',
            'List of Disconnected Con.',
            'Penalty Report (Detailed)',
            'Penalty Report (Summary)',
            'Franchise Tax Report(Detailed)',
            'Franchise Tax Report(Summary)',
            'Monthly Billing Summary',
            'Billing Report',
            'Billed Con by Category and Size',
            'Consumption by Category & Size',
            'All Payments',
            'Online Payments',
            'Unpaid Bills',
            'Paid Bills',
            'Readings (90days)',
            'Readings (Detailed)',
            'Senior Count',
            'List of Concessionaires',
            'List of Active',
            'List of Inactive',
            'Book Summary Report',
            'Monthly Billing Matrix Report',
        ];

        $zones = ConcessionerAccount::select('zone')
            ->distinct()
            ->orderBy('zone', 'asc')
            ->pluck('zone');

        $reportCategories = [
            'Billing & Collection' => [
                'Monthly Billing Summary',
                'Billing Report',
                'All Payments',
                'Online Payments',
                'Unpaid Bills',
                'Paid Bills',
                'Book Summary Report',
                'Monthly Billing Matrix Report',
            ],
            'Accounts & Concessionaires' => [
                'List of Disconnected Con.',
                'Billed Con by Category and Size',
                'Senior Count',
                'List of Concessionaires',
                'List of Active',
                'List of Inactive',
            ],
            'Readings & Consumption' => [
                'Readings (90days)',
                'Readings (Detailed)',
                'Consumption by Category & Size',
            ],
            'Penalties & Delinquency' => [
                'Ageing (Detailed)',
                'Ageing (Summary)',
                'Ageing (Recap)',
                'Penalty Report (Detailed)',
                'Penalty Report (Summary)',
            ],
            'Financial & Tax' => [
                'Franchise Tax Report(Detailed)',
                'Franchise Tax Report(Summary)',
            ],
        ];

        return view('reports.download-index', compact('availableReports', 'reportCategories', 'zones'));
    }

    private function addReportHeader($sheet, string $reportName, $startDate = null, $endDate = null): void
    {
        $hasDistrictHeader = strtoupper(trim((string) $sheet->getCell('A1')->getValue())) === 'STA RITA WATER DISTRICT';
        $lastColumn = $sheet->getHighestDataColumn();
        if ($lastColumn === 'A') {
            $lastColumn = 'B';
        }


        if (!$hasDistrictHeader) {
            $sheet->insertNewRowBefore(1, 3);
        }

        $sheet->setCellValue('A1', 'STA RITA WATER DISTRICT');
        $sheet->setCellValue('A2', $reportName);
        $sheet->setCellValue('A3', $this->reportPeriodHeading($startDate, $endDate));
        foreach ([1, 2, 3] as $row) {
            $cell = 'A' . $row;
            $mergeRange = "A{$row}:{$lastColumn}{$row}";
            if (!in_array($mergeRange, $sheet->getMergeCells(), true)) {
                $sheet->mergeCells($mergeRange);
            }
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
        }
        $sheet->getStyle('A1')->getFont()->setSize(14);
    }

    private function reportPeriodHeading($startDate = null, $endDate = null): string
    {
        $start = $startDate ? Carbon::parse($startDate) : ($endDate ? Carbon::parse($endDate) : now());
        $end = $endDate ? Carbon::parse($endDate) : $start->copy();

        return $start->format('F Y') === $end->format('F Y')
            ? 'FOR THE MONTH OF ' . strtoupper($start->format('F Y'))
            : 'FOR THE PERIOD OF ' . strtoupper($start->format('M d, Y')) . ' TO ' . strtoupper($end->format('M d, Y'));
    }

    private function writeRowsInChunks($sheet, array $rows, int $startRow, int $chunkSize = 500): void
    {
        $total = count($rows);
        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $chunk = array_map('array_values', array_slice($rows, $offset, $chunkSize));
            $sheet->fromArray($chunk, null, 'A' . ($startRow + $offset));
            unset($chunk);
        }
    }

    protected function generateMatrixReport($startDate, $endDate, $zone)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $title = 'MONTHLY BILLING SUMMARY';
        if ($startDate) {
            $title .= ' (' . \Carbon\Carbon::parse($startDate)->format('F Y') . ')';
        }

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:Q1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        $sheet->setCellValue('A3', 'ZONES');
        $sheet->setCellValue('B3', 'RESIDENTIAL'); $sheet->mergeCells('B3:D3');
        $sheet->setCellValue('E3', 'GOVERNMENT');  $sheet->mergeCells('E3:G3');
        $sheet->setCellValue('H3', 'COMMERCIAL A'); $sheet->mergeCells('H3:J3');
        $sheet->setCellValue('K3', 'COMMERCIAL B'); $sheet->mergeCells('K3:M3');
        $sheet->setCellValue('N3', 'COMMERCIAL C'); $sheet->mergeCells('N3:P3');
        $sheet->setCellValue('Q3', 'TOTAL');

        $sheet->fromArray([[
            'ZONE',
            'No.', 'Cu.M', 'Amount',
            'No.', 'Cu.M', 'Amount',
            'No.', 'Cu.M', 'Amount',
            'No.', 'Cu.M', 'Amount',
            'No.', 'Cu.M', 'Amount',
            'Total Amount'
        ]], null, 'A4');

        $readings = Reading::with(['concessionaire', 'bill'])
            ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
            ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $q->whereBetween('bill.bill_period_from', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                }
            })
            ->get();

        $matrix = [];

        foreach ($readings as $reading) {
            $bill = $reading->bill;
            if (!$bill) continue;

            $zoneKey = 'ZONE ' . ($reading->zone ?? 'N/A');
            $type = strtoupper(trim(optional($reading->concessionaire)->property_type ?? ''));

            if (str_contains($type, 'RESIDENTIAL')) $cat = 'RES';
            elseif (str_contains($type, 'GOVERNMENT')) $cat = 'GOV';
            elseif (str_contains($type, 'COMMERCIAL A')) $cat = 'A';
            elseif (str_contains($type, 'COMMERCIAL B')) $cat = 'B';
            elseif (str_contains($type, 'COMMERCIAL C')) $cat = 'C';
            else continue;

            if (!isset($matrix[$zoneKey][$cat])) {
                $matrix[$zoneKey][$cat] = ['count'=>0,'cum'=>0,'amt'=>0];
            }

            $matrix[$zoneKey][$cat]['count']++;
            $matrix[$zoneKey][$cat]['cum'] += (float) $reading->consumption;
            $matrix[$zoneKey][$cat]['amt'] += (float) $bill->amount;
        }

        $row = 5;

        $totals = [
            'RES'=>['count'=>0,'cum'=>0,'amt'=>0],
            'GOV'=>['count'=>0,'cum'=>0,'amt'=>0],
            'A'=>['count'=>0,'cum'=>0,'amt'=>0],
            'B'=>['count'=>0,'cum'=>0,'amt'=>0],
            'C'=>['count'=>0,'cum'=>0,'amt'=>0],
            'total' => 0
        ];

        foreach ($matrix as $zone => $cats) {

            $res = $cats['RES'] ?? ['count'=>0,'cum'=>0,'amt'=>0];
            $gov = $cats['GOV'] ?? ['count'=>0,'cum'=>0,'amt'=>0];
            $a   = $cats['A']   ?? ['count'=>0,'cum'=>0,'amt'=>0];
            $b   = $cats['B']   ?? ['count'=>0,'cum'=>0,'amt'=>0];
            $c   = $cats['C']   ?? ['count'=>0,'cum'=>0,'amt'=>0];

            $zoneTotal = $res['amt'] + $gov['amt'] + $a['amt'] + $b['amt'] + $c['amt'];

            foreach (['RES'=>$res,'GOV'=>$gov,'A'=>$a,'B'=>$b,'C'=>$c] as $key => $val) {
                $totals[$key]['count'] += $val['count'];
                $totals[$key]['cum'] += $val['cum'];
                $totals[$key]['amt'] += $val['amt'];
            }
            $totals['total'] += $zoneTotal;

            $totals['RES']['count'] += $res['count'];
            $totals['RES']['cum']   += $res['cum'];
            $totals['RES']['amt']   += $res['amt'];

            $totals['GOV']['count'] += $gov['count'];
            $totals['GOV']['cum']   += $gov['cum'];
            $totals['GOV']['amt']   += $gov['amt'];

            $totals['A']['count'] += $a['count'];
            $totals['A']['cum']   += $a['cum'];
            $totals['A']['amt']   += $a['amt'];

            $totals['B']['count'] += $b['count'];
            $totals['B']['cum']   += $b['cum'];
            $totals['B']['amt']   += $b['amt'];

            $totals['C']['count'] += $c['count'];
            $totals['C']['cum']   += $c['cum'];
            $totals['C']['amt']   += $c['amt'];

            $totals['total'] += $total;

            $sheet->fromArray([[
                'TOTAL',

                $totals['RES']['count'], $totals['RES']['cum'], $totals['RES']['amt'],
                $totals['GOV']['count'], $totals['GOV']['cum'], $totals['GOV']['amt'],
                $totals['A']['count'],   $totals['A']['cum'],   $totals['A']['amt'],
                $totals['B']['count'],   $totals['B']['cum'],   $totals['B']['amt'],
                $totals['C']['count'],   $totals['C']['cum'],   $totals['C']['amt'],

                $totals['total']
            ]], null, "A{$row}");

            $sheet->fromArray([[
                $zone,

                $res['count'], $res['cum'], $res['amt'],
                $gov['count'], $gov['cum'], $gov['amt'],
                $a['count'],   $a['cum'],   $a['amt'],
                $b['count'],   $b['cum'],   $b['amt'],
                $c['count'],   $c['cum'],   $c['amt'],

                $zoneTotal
            ]], null, "A{$row}");


            $row++;
        }

        $sheet->fromArray([[
            'TOTAL',

            $totals['RES']['count'], $totals['RES']['cum'], $totals['RES']['amt'],
            $totals['GOV']['count'], $totals['GOV']['cum'], $totals['GOV']['amt'],
            $totals['A']['count'],   $totals['A']['cum'],   $totals['A']['amt'],
            $totals['B']['count'],   $totals['B']['cum'],   $totals['B']['amt'],
            $totals['C']['count'],   $totals['C']['cum'],   $totals['C']['amt'],

            $totals['total']
        ]], null, "A{$row}");

        $sheet->getStyle("A{$row}:Q{$row}")
            ->getFont()->setBold(true)
            ->getColor()->setARGB('FF0000')
            ->setRGB('FF0000');

        $sheet->getStyle('A3:Q4')->getFont()->setBold(true);
        $sheet->getStyle('A3:Q4')->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center');

        $sheet->getStyle("A3:Q{$row}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sheet->getStyle('A3:Q4')
            ->getBorders()
            ->getOutline()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        foreach (range('A','Q') as $col) {
            $sheet->getColumnDimension($col)->setWidth($col === 'A' ? 18 : 14);
        }

        return [
            'type' => 'formatted',
            'spreadsheet' => $spreadsheet
        ];
    }

   public function generateFile(Request $request)
    {
        $request->validate([
            'reports' => 'required|array|min:1',
            'mode' => 'required|in:combined,separate',
            'format' => 'required|in:xlsx,csv,plain',
            'zone' => 'required',
            'sheet_grouping' => 'required|in:all,monthly',
        ]);

        Settings::setCache(Cache::store(config('cache.default')));

        $reports = $request->input('reports', []);
        $mode = $request->input('mode');
        $format = $request->input('format');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $zone = $request->input('zone');
        $classification = $request->input('classification');
        $sheetGrouping = $request->input('sheet_grouping', 'all');

        // This simple directory report can be streamed directly to CSV. Avoid
        // hydrating every account and every spreadsheet cell in memory.
        if ($format === 'csv' && count($reports) === 1 && $reports[0] === 'List of Concessionaires') {
            return $this->streamConcessionaireListCsv($zone);
        }

        $months = [];

        if (
            $sheetGrouping === 'monthly' &&
            $startDate &&
            $endDate
        ) {
            $currentMonth = Carbon::parse($startDate)->startOfMonth();
            $lastMonth = Carbon::parse($endDate)->startOfMonth();

            while ($currentMonth->lte($lastMonth)) {
                $months[] = [
                    'name' => $currentMonth->format('F Y'),
                    'start' => $currentMonth->copy()->startOfMonth()->format('Y-m-d'),
                    'end' => $currentMonth->copy()->endOfMonth()->format('Y-m-d'),
                ];

                $currentMonth->addMonth();
            }
        }

        if ($mode === 'separate') {

            $files = [];

            foreach ($reports as $reportName) {
                $data = $this->fetchReportsFromDb(
                    [$reportName],
                    $startDate,
                    $endDate,
                    $zone,
                    $classification
                )[$reportName] ?? [];

                if (is_array($data) && isset($data['type']) && $data['type'] === 'formatted') {

                    $spreadsheet = $data['spreadsheet'];
                    foreach ($spreadsheet->getAllSheets() as $sheet) {
                        $this->addReportHeader($sheet, $reportName, $startDate, $endDate);
                    }

                    $fileName = str_replace(['/', '\\'], '-', $reportName)
                        . '-' . now()->format('Ymd_His') . '.' . $format;

                    $filePath = storage_path("app/reports/{$fileName}");

                    $writer = $format === 'csv'
                        ? new Csv($spreadsheet)
                        : new Xlsx($spreadsheet);

                    $writer->save($filePath);

                    $files[] = $filePath;
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $data);
                    continue;
                }

                if (is_array($data) && !isset($data[0])) {

                    $spreadsheet = new Spreadsheet();
                    $first = true;

                    foreach ($data as $sheetName => $rows) {

                        $rows = array_values($rows);

                        if ($first) {
                            $sheet = $spreadsheet->getActiveSheet();
                            $sheet->setTitle(substr($sheetName, 0, 31));
                            $first = false;
                        } else {
                            $sheet = $spreadsheet->createSheet();
                            $sheet->setTitle(substr($sheetName, 0, 31));
                        }

                        $this->addReportHeader($sheet, $reportName, $startDate, $endDate);
                        if (!empty($rows) && isset($rows[0])) {
                            $headers = array_keys($rows[0]);
                            $sheet->fromArray([$headers], null, 'A4');
                            $this->writeRowsInChunks($sheet, $rows, 5);
                        } else {
                            $sheet->setCellValue('A4', 'No data available');
                        }
                    }

                    if ($first) {
                        $sheet = $spreadsheet->getActiveSheet();
                        $sheet->setTitle(substr($reportName, 0, 31));
                        $this->addReportHeader($sheet, $reportName, $startDate, $endDate);
                        $sheet->setCellValue('A4', 'No data available');
                    }

                    $fileName = str_replace(['/', '\\'], '-', $reportName)
                        . '-' . now()->format('Ymd_His') . '.' . $format;

                    $filePath = storage_path("app/reports/{$fileName}");

                    $writer = $format === 'csv'
                        ? new Csv($spreadsheet)
                        : new Xlsx($spreadsheet);

                    $writer->save($filePath);

                    $files[] = $filePath;
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet, $data);
                    continue;
                }

                $filePath = $this->createFile($reportName, $data, $format, $startDate, $endDate);
                $files[] = $filePath;
                unset($data);
            }

            if (count($files) > 1) {
                $zipName = 'reports-' . now()->format('Ymd_His') . '.zip';
                $zipPath = storage_path("app/reports/{$zipName}");

                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                    foreach ($files as $f) {
                        $zip->addFile($f, basename($f));
                    }
                    $zip->close();

                    foreach ($files as $file) {
                        @unlink($file);
                    }
                }

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return response()->download($files[0])->deleteFileAfterSend(true);
        }

        $spreadsheet = new Spreadsheet();
        $firstSheet = true;

        foreach ($reports as $reportName) {
            $dateRanges = [];

            if ($sheetGrouping === 'monthly' && $startDate && $endDate) {

                $currentMonth = \Carbon\Carbon::parse($startDate)->startOfMonth();
                $lastMonth = \Carbon\Carbon::parse($endDate)->startOfMonth();

                while ($currentMonth->lte($lastMonth)) {

                    $monthStart = $currentMonth->copy()->startOfMonth();
                    $monthEnd = $currentMonth->copy()->endOfMonth();

                    if ($monthStart->lt(\Carbon\Carbon::parse($startDate))) {
                        $monthStart = \Carbon\Carbon::parse($startDate);
                    }

                    if ($monthEnd->gt(\Carbon\Carbon::parse($endDate))) {
                        $monthEnd = \Carbon\Carbon::parse($endDate);
                    }

                    $dateRanges[] = [
                        'name'  => $currentMonth->format('F Y'),
                        'start' => $monthStart->format('Y-m-d'),
                        'end'   => $monthEnd->format('Y-m-d'),
                    ];

                    $currentMonth->addMonth();
                }

            } else {

                $dateRanges[] = [
                    'name'  => null,
                    'start' => $startDate,
                    'end'   => $endDate,
                ];
            }

            foreach ($dateRanges as $dateRange) {

                $data = $this->fetchReportsFromDb(
                    [$reportName],
                    $dateRange['start'],
                    $dateRange['end'],
                    $zone,
                    $classification
                )[$reportName] ?? [];

                $sheetPrefix = $reportName;

                if ($dateRange['name']) {
                    $sheetPrefix .= ' - ' . $dateRange['name'];
                }

                if (
                    is_array($data) &&
                    isset($data['type']) &&
                    $data['type'] === 'formatted'
                ) {

                    $sourceSheets = $data['spreadsheet']->getAllSheets();

                    if (count($sourceSheets) === 1) {

                        $sourceSheets[0]->setTitle(
                            substr($sheetPrefix, 0, 31)
                        );
                    }

                    foreach ($sourceSheets as $sourceSheet) {

                        $baseTitle = $sourceSheet->getTitle();

                        if ($dateRange['name']) {
                            $baseTitle .= ' - ' . $dateRange['name'];
                        }

                        $baseTitle = substr($baseTitle, 0, 31);

                        $title = $baseTitle;
                        $suffix = 1;

                        while ($spreadsheet->sheetNameExists($title)) {

                            $suffixText = ' ' . $suffix++;

                            $title = substr(
                                $baseTitle,
                                0,
                                31 - strlen($suffixText)
                            ) . $suffixText;
                        }

                        $sourceSheet->setTitle($title);
                        $this->addReportHeader($sourceSheet, $reportName, $dateRange['start'], $dateRange['end']);

                        if ($firstSheet) {

                            $spreadsheet->removeSheetByIndex(0);

                            $spreadsheet->addExternalSheet(
                                $sourceSheet,
                                0
                            );

                            $firstSheet = false;

                        } else {

                            $spreadsheet->addExternalSheet(
                                $sourceSheet
                            );
                        }
                    }

                    $data['spreadsheet']->disconnectWorksheets();

                    unset($data);

                    continue;
                }

                if (
                    is_array($data) &&
                    !isset($data[0])
                ) {

                    foreach ($data as $subSheetName => $rows) {

                        $rows = array_values($rows);

                        $sheetName = $subSheetName;

                        if ($dateRange['name']) {
                            $sheetName .= ' - ' . $dateRange['name'];
                        }

                        $sheetName = substr($sheetName, 0, 31);
                        $title = $sheetName;
                        $suffix = 1;

                        while ($spreadsheet->sheetNameExists($title)) {

                            $suffixText = ' ' . $suffix++;

                            $title = substr(
                                $sheetName,
                                0,
                                31 - strlen($suffixText)
                            ) . $suffixText;
                        }

                        if ($firstSheet) {

                            $sheet = $spreadsheet->getActiveSheet();

                            $sheet->setTitle($title);

                            $firstSheet = false;

                        } else {

                            $sheet = $spreadsheet->createSheet();

                            $sheet->setTitle($title);
                        }

                        $this->addReportHeader($sheet, $reportName, $dateRange['start'], $dateRange['end']);
                        if (
                            !empty($rows) &&
                            isset($rows[0])
                        ) {

                            $headers = array_keys($rows[0]);
                            $sheet->fromArray([$headers], null, 'A4');
                            $this->writeRowsInChunks($sheet, $rows, 5);
                        } else {
                            $sheet->setCellValue('A4', 'No data available');
                        }
                    }

                    if ($firstSheet) {
                        $sheet = $spreadsheet->getActiveSheet();
                        $sheet->setTitle(substr($sheetPrefix, 0, 31));
                        $this->addReportHeader($sheet, $reportName, $dateRange['start'], $dateRange['end']);
                        $sheet->setCellValue('A4', 'No data available');
                        $firstSheet = false;
                    }

                    unset($data);

                    continue;
                }

                if (!array_is_list($data)) {
                    $data = array_values($data);
                }

                $sheetName = substr($sheetPrefix, 0, 31);
                $title = $sheetName;
                $suffix = 1;

                while ($spreadsheet->sheetNameExists($title)) {

                    $suffixText = ' ' . $suffix++;

                    $title = substr(
                        $sheetName,
                        0,
                        31 - strlen($suffixText)
                    ) . $suffixText;
                }


                if ($firstSheet) {
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle($title);
                    $firstSheet = false;
                } else {
                    $sheet = $spreadsheet->createSheet();
                    $sheet->setTitle($title);
                }

                if (
                    !empty($data) &&
                    isset($data[0])
                ) {

                    $headers = array_keys($data[0]);
                    $this->addReportHeader($sheet, $reportName, $dateRange['start'], $dateRange['end']);
                    $sheet->fromArray([$headers], null, 'A4');
                    $this->writeRowsInChunks($sheet, $data, 5);
                } else {
                    $this->addReportHeader($sheet, $reportName, $dateRange['start'], $dateRange['end']);
                    $sheet->setCellValue('A4', 'No data available');
                }

                unset($data);
            }
        }

        $fileName = 'combined-reports-' . now()->format('Ymd_His') . '.' . $format;
        $filePath = storage_path("app/reports/{$fileName}");

        $writer = $format === 'csv'
            ? new Csv($spreadsheet)
            : new Xlsx($spreadsheet);

            if ($spreadsheet->getSheetCount() === 0) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('EMPTY');
        $sheet->setCellValue('A1', 'No data available');
    }

        $writer->save($filePath);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    private function streamConcessionaireListCsv($zone)
    {
        $fileName = 'list-of-concessionaires-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($zone) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Account Number', 'Name', 'Address', 'Status'], ',', '"', '');

            $accounts = DB::table('concessioner_accounts')
                ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')
                ->when($zone !== 'all', fn ($query) => $query->where('concessioner_accounts.zone', $zone))
                ->select([
                    'concessioner_accounts.id',
                    'concessioner_accounts.account_no',
                    'users.name as user_name',
                    'concessioner_accounts.address',
                    'concessioner_accounts.status',
                ])
                ->orderBy('concessioner_accounts.id');

            $accounts->chunkById(2000, function ($batch) use ($output) {
                foreach ($batch as $account) {
                    $status = match ($account->status) {
                        'AB' => 'Active',
                        'ID', 'IV', 'BL' => 'Inactive',
                        default => 'N/A',
                    };

                    fputcsv($output, [
                        $account->account_no,
                        $account->user_name ?? 'N/A',
                        $account->address ?? 'N/A',
                        $status,
                    ], ',', '"', '');
                }

                fflush($output);
            }, 'concessioner_accounts.id', 'id');

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    protected function fetchReportsFromDb(array $reports, $startDate = null, $endDate = null, $zone = null, $classification = null)
    {
        $result = [];

        foreach ($reports as $report) {
            switch ($report) {
                case 'Ageing (Detailed)':
                    $anchorDate = $endDate
                        ? Carbon::parse($endDate)
                        : ($startDate ? Carbon::parse($startDate) : now());

                    $cutoffDate = $anchorDate->copy()->endOfMonth();

                    $oldestBillPeriod = Bill::query()
                        ->join('readings', 'readings.id', '=', 'bill.reading_id')
                        ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                        ->where('bill.isPaid', 0)
                        ->where('bill.amount', '>', 0)
                        ->whereIn('concessioner_accounts.status', ['AB', 'ID', 'BL', 'IV'])
                        ->where('bill.bill_period_to', '<=', $cutoffDate->format('Y-m-d H:i:s'))
                        ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                        ->min('bill.bill_period_to');

                    $oldestMonth = $oldestBillPeriod
                        ? Carbon::parse($oldestBillPeriod)->startOfMonth()
                        : $anchorDate->copy()->subMonths(3)->startOfMonth();

                    $monthCount = max(4, $oldestMonth->diffInMonths($anchorDate->copy()->startOfMonth()) + 1);

                    $months = collect(range(0, $monthCount - 1))
                        ->map(fn ($offset) => $anchorDate->copy()->subMonths($offset))
                        ->values();

                    $monthStart = $months->last()->copy()->startOfMonth();
                    $monthEnd = $cutoffDate;

                    $monthSelects = $months->map(function ($month, $index) {
                        $start = $month->copy()->startOfMonth()->format('Y-m-d H:i:s');
                        $end = $month->copy()->endOfMonth()->format('Y-m-d H:i:s');
                        $alias = 'month_' . $index . '_amount';

                        return "SUM(CASE WHEN bill.bill_period_to BETWEEN '{$start}' AND '{$end}' THEN CAST(bill.amount AS DECIMAL(12,2)) ELSE 0 END) AS {$alias}";
                    })->implode(",\n                        ");

                    $accounts = Bill::query()
                        ->join('readings', 'readings.id', '=', 'bill.reading_id')
                        ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                        ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')
                        ->where('bill.isPaid', 0)
                        ->where('bill.amount', '>', 0)
                        ->whereIn('concessioner_accounts.status', ['AB', 'ID', 'BL', 'IV'])
                        ->where('bill.bill_period_to', '>=', $monthStart->format('Y-m-d H:i:s'))
                        ->where('bill.bill_period_to', '<=', $monthEnd->format('Y-m-d H:i:s'))
                        ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                        ->selectRaw("
                            concessioner_accounts.zone AS zone,
                            readings.account_no AS account_no,
                            users.name AS name,
                            concessioner_accounts.status AS status,
                            concessioner_accounts.sequence_no AS sequence_no,
                            {$monthSelects},
                            SUM(CAST(bill.amount AS DECIMAL(12,2))) AS total
                        ")
                        ->groupBy(
                            'concessioner_accounts.zone',
                            'readings.account_no',
                            'users.name',
                            'concessioner_accounts.status',
                            'concessioner_accounts.sequence_no'
                        )
                        ->orderBy('concessioner_accounts.zone', 'asc')
                        ->orderBy('concessioner_accounts.sequence_no', 'asc')
                        ->get();

                    $rows = [];
                    foreach ($accounts as $account) {
                        $monthAmounts = [];

                        foreach ($months as $index => $month) {
                            $monthAmounts[strtoupper($month->format('F'))] = $account->{'month_' . $index . '_amount'} ?? 0;
                        }

                        $rows[] = array_merge([
                            'zone' => $account->zone ?? 'N/A',
                            'account_number' => $account->account_no ?? 'N/A',
                            'name' => $account->name ?? 'N/A',
                            'status' => $account->status ?? 'N/A',
                        ], $monthAmounts, [
                            'total' => $account->total ?? 0,
                        ]);
                    }
                    $result[$report] = $rows;
                    break;

                case 'Ageing (Summary)':
                $query = Reading::with('bill')
                    ->select('readings.*')
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->orderBy('zone', 'asc')
                    ->get()
                    ->groupBy('zone');

                $rows = [];
                $totals = [
                    'current' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                ];

                foreach ($query as $zoneKey => $readings) {
                    $zoneSummary = [
                        'current' => 0,
                        '1_30' => 0,
                        '31_60' => 0,
                        '61_90' => 0,
                        'over_90' => 0,
                    ];

                    // Avoid redundant bill reads per account
                    $uniqueAccounts = $readings->unique('account_no');

                    foreach ($uniqueAccounts as $reading) {
                        $bill = $reading->bill;
                        if (!$bill) continue;

                        $dueDate = Carbon::parse($bill->due_date);
                        $daysOverdue = $dueDate->diffInDays(now(), false);
                        $amount = (float) ($bill->amount ?? 0);

                        if ($daysOverdue <= 0) {
                            $zoneSummary['current'] += $amount;
                        } elseif ($daysOverdue <= 30) {
                            $zoneSummary['1_30'] += $amount;
                        } elseif ($daysOverdue <= 60) {
                            $zoneSummary['31_60'] += $amount;
                        } elseif ($daysOverdue <= 90) {
                            $zoneSummary['61_90'] += $amount;
                        } else {
                            $zoneSummary['over_90'] += $amount;
                        }
                    }

                    $total = array_sum($zoneSummary);

                    // Update grand totals
                    foreach ($zoneSummary as $key => $value) {
                        $totals[$key] += $value;
                    }
                    $totals['total'] += $total;

                    $rows[] = [
                        'zone' => $zoneKey,
                        'current' => $zoneSummary['current'],
                        '1_30' => $zoneSummary['1_30'],
                        '31_60' => $zoneSummary['31_60'],
                        '61_90' => $zoneSummary['61_90'],
                        'over_90' => $zoneSummary['over_90'],
                        'total' => $total,
                    ];
                }

                usort($rows, fn($a, $b) => (int)$a['zone'] <=> (int)$b['zone']);

                $rows[] = [
                    'zone' => 'TOTAL',
                    'current' => $totals['current'],
                    '1_30' => $totals['1_30'],
                    '31_60' => $totals['31_60'],
                    '61_90' => $totals['61_90'],
                    'over_90' => $totals['over_90'],
                    'total' => $totals['total'],
                ];

                $result[$report] = $rows;
                break;

                case 'Ageing (Recap)':
                $readings = Reading::with(['bill', 'concessionaire.propertyType'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->get();

                $grouped = $readings->groupBy(function($reading) {
                    return optional(optional($reading->concessionaire)->propertyType)->name
                        ?? optional($reading->concessionaire)->property_type
                        ?? 'UNCLASSIFIED';
                });

                $rows = [];
                $totals = [
                    'customers' => 0,
                    'current' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                ];

                foreach ($grouped as $classification => $groupReadings) {
                    $summary = [
                        'customers' => $groupReadings->unique('account_no')->count(),
                        'current' => 0,
                        '1_30' => 0,
                        '31_60' => 0,
                        '61_90' => 0,
                        'over_90' => 0,
                    ];

                    foreach ($groupReadings->unique('account_no') as $reading) {
                        $bill = $reading->bill;
                        if (!$bill) continue;

                        $dueDate = Carbon::parse($bill->due_date);
                        $daysOverdue = $dueDate->diffInDays(now(), false);
                        $amount = (float) ($bill->amount ?? 0);

                        if ($daysOverdue <= 0) $summary['current'] += $amount;
                        elseif ($daysOverdue <= 30) $summary['1_30'] += $amount;
                        elseif ($daysOverdue <= 60) $summary['31_60'] += $amount;
                        elseif ($daysOverdue <= 90) $summary['61_90'] += $amount;
                        else $summary['over_90'] += $amount;
                    }

                    $summary['total'] = $summary['current'] + $summary['1_30'] + $summary['31_60'] + $summary['61_90'] + $summary['over_90'];

                    foreach ($totals as $key => $value) {
                        if (isset($summary[$key])) $totals[$key] += $summary[$key];
                    }

                    $rows[] = array_merge(['classification' => $classification], $summary);
                }

                $rows[] = [
                    'classification' => 'GRAND TOTAL',
                    'customers' => $totals['customers'],
                    'current' => $totals['current'],
                    '1_30' => $totals['1_30'],
                    '31_60' => $totals['31_60'],
                    '61_90' => $totals['61_90'],
                    'over_90' => $totals['over_90'],
                    'total' => $totals['total'],
                ];

                $result[$report] = $rows;
                break;

                case 'List of Disconnected Con.':
                $bills = Bill::query()
                    ->join('readings', 'readings.id', '=', 'bill.reading_id')
                    ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                    ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')
                    ->whereIn('concessioner_accounts.status', ['ID', 'IV', 'BL'])
                    ->where('bill.isPaid', 0)
                    ->where('bill.amount', '>', 0)
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [
                            $startDate . ' 00:00:00',
                            $endDate . ' 23:59:59',
                        ]);
                    })
                    ->selectRaw("
                        users.name as consumer_name,
                        concessioner_accounts.zone,
                        concessioner_accounts.address,
                        concessioner_accounts.account_no,
                        concessioner_accounts.property_type,
                        concessioner_accounts.status,
                        concessioner_accounts.sequence_no,
                        COALESCE(SUM(CAST(bill.amount AS DECIMAL(12,2))), 0) AS amount,
                        MIN(bill.due_date) AS due_date
                    ")
                    ->groupBy(
                        'users.name',
                        'concessioner_accounts.zone',
                        'concessioner_accounts.address',
                        'concessioner_accounts.account_no',
                        'concessioner_accounts.property_type',
                        'concessioner_accounts.status',
                        'concessioner_accounts.sequence_no'
                    )
                    ->orderBy('concessioner_accounts.zone', 'asc')
                    ->orderBy('concessioner_accounts.sequence_no', 'asc')
                    ->get();

                $rows = [];

                foreach ($bills as $bill) {
                    $arrears = (float) ($bill->amount ?? 0);
                    $monthInArrears = $bill->due_date ? now()->diffInMonths(Carbon::parse($bill->due_date)) : 0;

                    $rows[] = [
                        'Name of Consumers' => $bill->consumer_name ?? 'N/A',
                        'Zone' => $bill->zone ?? 'N/A',
                        'Service Address' => $bill->address ?? 'N/A',
                        'Account no.' => $bill->account_no ?? 'N/A',
                        'Type' => $bill->property_type ?? 'N/A',
                        'Status' => $bill->status ?? 'N/A',
                        'Arrears' => $arrears,
                        'Month in arrears' => $monthInArrears,
                    ];
                }

                $totalArrears = collect($rows)->sum('Arrears');
                $rows[] = [
                    'Name of Consumers' => 'TOTAL',
                    'Zone' => '',
                    'Service Address' => '',
                    'Account no.' => '',
                    'Type' => '',
                    'Status' => '',
                    'Arrears' => $totalArrears,
                    'Month in arrears' => '',
                ];

                $result[$report] = $rows;
                break;

                case 'Penalty Report (Detailed)':
                $readings = Reading::with(['bill', 'concessionaire.user'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->get();

                $rows = [];

                $totals = [
                    'Current Penalty' => 0,
                    '1-30 days' => 0,
                    '31-60 days' => 0,
                    '61-90 days' => 0,
                    'Waterbill Total' => 0,
                ];

                foreach ($readings->unique('account_no') as $reading) {
                    $bill = $reading->bill;
                    if (!$bill) continue;

                    $accountNo = $reading->account_no;
                    $name = optional(optional($reading->concessionaire)->user)->name ?? 'N/A';
                    $penalty = floatval($bill->penalty ?? 0);
                    $amount = floatval($bill->amount ?? 0);

                    $dueDate = Carbon::parse($bill->due_date);
                    $daysOverdue = $dueDate->diffInDays(now(), false);

                    $buckets = [
                        'Current Penalty' => 0,
                        '1-30 days' => 0,
                        '31-60 days' => 0,
                        '61-90 days' => 0,
                    ];

                    if ($daysOverdue <= 0) {
                        $buckets['Current Penalty'] = $penalty;
                    } elseif ($daysOverdue <= 30) {
                        $buckets['1-30 days'] = $penalty;
                    } elseif ($daysOverdue <= 60) {
                        $buckets['31-60 days'] = $penalty;
                    } elseif ($daysOverdue <= 90) {
                        $buckets['61-90 days'] = $penalty;
                    }

                    $waterbillTotal = array_sum($buckets) + $amount;

                    $rows[] = [
                        'Account Number' => $accountNo,
                        'Name' => $name,
                        'Current Penalty' => $buckets['Current Penalty'],
                        '1-30 days' => $buckets['1-30 days'],
                        '31-60 days' => $buckets['31-60 days'],
                        '61-90 days' => $buckets['61-90 days'],
                        'Waterbill Total' => $waterbillTotal,
                    ];

                    foreach ($buckets as $label => $value) {
                        $totals[$label] += $value;
                    }
                    $totals['Waterbill Total'] += $waterbillTotal;
                }

                $rows[] = [
                    'Account Number' => 'TOTAL',
                    'Name' => '',
                    'Current Penalty' => $totals['Current Penalty'],
                    '1-30 days' => $totals['1-30 days'],
                    '31-60 days' => $totals['31-60 days'],
                    '61-90 days' => $totals['61-90 days'],
                    'Waterbill Total' => $totals['Waterbill Total'],
                ];

                if (!empty($rows)) {
                    $result[$report] = $rows;
                } else {
                    $result[$report] = [['Account Number' => 'No records found']];
                }

                break;

                case 'Penalty Report (Summary)':
                $readings = Reading::with(['bill', 'concessionaire.propertyType'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->get();

                $grouped = $readings->groupBy(function($reading) {
                    return optional(optional($reading->concessionaire)->propertyType)->name
                        ?? optional($reading->concessionaire)->property_type
                        ?? 'UNCLASSIFIED';
                });

                $rows = [];
                $totals = [
                    'Number of Customer' => 0,
                    'Current Penalty' => 0,
                    '1-30 days' => 0,
                    '31-60 days' => 0,
                    '61-90 days' => 0,
                    'Over 90 days' => 0,
                    'Penalty Total' => 0,
                ];

                foreach ($grouped as $classification => $groupReadings) {
                    $summary = [
                        'Number of Customer' => $groupReadings->unique('account_no')->count(),
                        'Current Penalty' => 0,
                        '1-30 days' => 0,
                        '31-60 days' => 0,
                        '61-90 days' => 0,
                        'Over 90 days' => 0,
                    ];

                    foreach ($groupReadings->unique('account_no') as $reading) {
                        $bill = $reading->bill;
                        if (!$bill) continue;

                        $penalty = floatval($bill->penalty ?? 0);
                        if ($penalty <= 0) continue;

                        $dueDate = Carbon::parse($bill->due_date);
                        $daysOverdue = $dueDate->diffInDays(now(), false);

                        if ($daysOverdue <= 0) $summary['Current Penalty'] += $penalty;
                        elseif ($daysOverdue <= 30) $summary['1-30 days'] += $penalty;
                        elseif ($daysOverdue <= 60) $summary['31-60 days'] += $penalty;
                        elseif ($daysOverdue <= 90) $summary['61-90 days'] += $penalty;
                        else $summary['Over 90 days'] += $penalty;
                    }

                    $summary['Penalty Total'] = $summary['Current Penalty'] + $summary['1-30 days'] +
                        $summary['31-60 days'] + $summary['61-90 days'] + $summary['Over 90 days'];

                    foreach ($totals as $key => $value) {
                        if (isset($summary[$key])) $totals[$key] += $summary[$key];
                    }

                    $rows[] = array_merge(['Classification' => $classification], $summary);
                }

                $rows[] = [
                    'Classification' => 'GRAND TOTAL',
                    'Number of Customer' => $totals['Number of Customer'],
                    'Current Penalty' => $totals['Current Penalty'],
                    '1-30 days' => $totals['1-30 days'],
                    '31-60 days' => $totals['31-60 days'],
                    '61-90 days' => $totals['61-90 days'],
                    'Over 90 days' => $totals['Over 90 days'],
                    'Penalty Total' => $totals['Penalty Total'],
                ];

                $result[$report] = $rows;
                break;

                case 'Franchise Tax Report(Detailed)':
                $readings = Reading::with(['bill', 'concessionaire.user'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->get();

                $rows = [];

                $totals = [
                    'Current Penalty' => 0,
                    '1-30 days' => 0,
                    '31-60 days' => 0,
                    '61-90 days' => 0,
                    'Franchise Tax Total' => 0,
                ];

                foreach ($readings->unique('account_no') as $reading) {
                    $bill = $reading->bill;
                    if (!$bill) continue;

                    $accountNo = $reading->account_no;
                    $name = optional(optional($reading->concessionaire)->user)->name ?? 'N/A';
                    $tax = floatval($bill->tax ?? 0);

                    $dueDate = Carbon::parse($bill->due_date);
                    $daysOverdue = $dueDate->diffInDays(now(), false);

                    $buckets = [
                        'Current Penalty' => 0,
                        '1-30 days' => 0,
                        '31-60 days' => 0,
                        '61-90 days' => 0,
                    ];

                    if ($daysOverdue <= 0) {
                        $buckets['Current Penalty'] = $tax;
                    } elseif ($daysOverdue <= 30) {
                        $buckets['1-30 days'] = $tax;
                    } elseif ($daysOverdue <= 60) {
                        $buckets['31-60 days'] = $tax;
                    } elseif ($daysOverdue <= 90) {
                        $buckets['61-90 days'] = $tax;
                    }

                    $franchiseTaxTotal = array_sum($buckets);

                    $rows[] = [
                        'Account Number' => $accountNo,
                        'Name' => $name,
                        'Current Penalty' => $buckets['Current Penalty'],
                        '1-30 days' => $buckets['1-30 days'],
                        '31-60 days' => $buckets['31-60 days'],
                        '61-90 days' => $buckets['61-90 days'],
                        'Franchise Tax Total' => $franchiseTaxTotal,
                    ];

                    foreach ($buckets as $label => $value) {
                        $totals[$label] += $value;
                    }
                    $totals['Franchise Tax Total'] += $franchiseTaxTotal;
                }

                $rows[] = [
                    'Account Number' => 'TOTAL',
                    'Name' => '',
                    'Current Penalty' => $totals['Current Penalty'],
                    '1-30 days' => $totals['1-30 days'],
                    '31-60 days' => $totals['31-60 days'],
                    '61-90 days' => $totals['61-90 days'],
                    'Franchise Tax Total' => $totals['Franchise Tax Total'],
                ];

                $result[$report] = !empty($rows) ? $rows : [['Account Number' => 'No records found']];

                break;

                case 'Franchise Tax Report(Summary)':
                $readings = Reading::with(['bill', 'concessionaire.propertyType'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->get();

                $grouped = $readings->groupBy(function($reading) {
                    return optional(optional($reading->concessionaire)->propertyType)->name
                        ?? optional($reading->concessionaire)->property_type
                        ?? 'UNCLASSIFIED';
                });

                $rows = [];
                $totals = [
                    'customers' => 0,
                    'current' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90' => 0,
                    'over_90' => 0,
                    'total' => 0,
                ];

                foreach ($grouped as $classification => $groupReadings) {
                    $uniqueAccounts = $groupReadings->unique('account_no');
                    $summary = [
                        'customers' => $uniqueAccounts->count(),
                        'current' => 0,
                        '1_30' => 0,
                        '31_60' => 0,
                        '61_90' => 0,
                        'over_90' => 0,
                    ];

                    foreach ($uniqueAccounts as $reading) {
                        $bill = $reading->bill;
                        if (!$bill) continue;

                        $tax = floatval($bill->tax ?? 0);
                        $dueDate = Carbon::parse($bill->due_date);
                        $daysOverdue = $dueDate->diffInDays(now(), false);

                        if ($daysOverdue <= 0) $summary['current'] += $tax;
                        elseif ($daysOverdue <= 30) $summary['1_30'] += $tax;
                        elseif ($daysOverdue <= 60) $summary['31_60'] += $tax;
                        elseif ($daysOverdue <= 90) $summary['61_90'] += $tax;
                        else $summary['over_90'] += $tax;
                    }

                    $summary['total'] = $summary['current'] + $summary['1_30'] + $summary['31_60'] + $summary['61_90'] + $summary['over_90'];
                    $totals['customers'] += $summary['customers'];
                    $totals['current'] += $summary['current'];
                    $totals['1_30'] += $summary['1_30'];
                    $totals['31_60'] += $summary['31_60'];
                    $totals['61_90'] += $summary['61_90'];
                    $totals['over_90'] += $summary['over_90'];
                    $totals['total'] += $summary['total'];

                    $rows[] = [
                        'Classification' => $classification,
                        'Number of Customer' => $summary['customers'],
                        'Current Penalty' => $summary['current'],
                        '1-30 days' => $summary['1_30'],
                        '31-60 days' => $summary['31_60'],
                        '61-90 days' => $summary['61_90'],
                        'Over 90 days' => $summary['over_90'],
                        'Franchise Tax Total' => $summary['total'],
                    ];
                }

                $rows[] = [
                    'Classification' => 'GRAND TOTAL',
                    'Number of Customer' => $totals['customers'],
                    'Current Penalty' => $totals['current'],
                    '1-30 days' => $totals['1_30'],
                    '31-60 days' => $totals['31_60'],
                    '61-90 days' => $totals['61_90'],
                    'Over 90 days' => $totals['over_90'],
                    'Franchise Tax Total' => $totals['total'],
                ];

                $result[$report] = $rows;
                break;

                case 'Monthly Billing Summary':
                $readings = Reading::with(['bill', 'concessionaire'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->orderBy('zone', 'asc')
                    ->get();

                if ($readings->isEmpty()) {
                    $result[$report] = [['Zone' => 'No data found']];
                    break;
                }

                $zonesGrouped = $readings->groupBy('zone');
                $rows = [];

                foreach ($zonesGrouped as $zoneKey => $zoneReadings) {
                    $byType = $zoneReadings->groupBy(fn($r) => $r->concessionaire->property_type ?? 'UNCLASSIFIED');
                    $rows[] = ['Zone' => "Zone $zoneKey", 'Connections' => '', 'Usage' => '', 'Water Bills' => '', 'Penalty' => '', 'Total' => ''];

                    $zoneTotals = ['connections' => 0, 'usage' => 0, 'waterBills' => 0, 'penalty' => 0, 'total' => 0];

                    foreach ($byType as $type => $group) {
                        $connections = $group->unique('account_no')->count();
                        $usage = $group->sum(fn($r) => max(0, ($r->present_reading ?? 0) - ($r->previous_reading ?? 0)));

                        $waterBills = $group->sum(fn($r) => floatval(optional($r->bill)->amount ?? 0));
                        $penalty = $group->sum(fn($r) => floatval(optional($r->bill)->penalty ?? 0));
                        $total = $waterBills + $penalty;

                        $zoneTotals['connections'] += $connections;
                        $zoneTotals['usage'] += $usage;
                        $zoneTotals['waterBills'] += $waterBills;
                        $zoneTotals['penalty'] += $penalty;
                        $zoneTotals['total'] += $total;

                        $rows[] = [
                            'Zone' => $type,
                            'Connections' => $connections,
                            'Usage' => $usage,
                            'Water Bills' => number_format($waterBills, 2),
                            'Penalty' => number_format($penalty, 2),
                            'Total' => number_format($total, 2),
                        ];
                    }

                    $rows[] = [
                        'Zone' => "TOTAL Zone $zoneKey",
                        'Connections' => $zoneTotals['connections'],
                        'Usage' => $zoneTotals['usage'],
                        'Water Bills' => number_format($zoneTotals['waterBills'], 2),
                        'Penalty' => number_format($zoneTotals['penalty'], 2),
                        'Total' => number_format($zoneTotals['total'], 2),
                    ];

                    $rows[] = ['', '', '', '', '', ''];
                }

                $result[$report] = $rows;
                break;

                case 'Billing Report':
                $query = Bill::query()
                    ->join('readings', 'readings.id', '=', 'bill.reading_id')
                    ->join(
                        'concessioner_accounts',
                        'concessioner_accounts.account_no',
                        '=',
                        'readings.account_no'
                    )
                    ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')
                    ->whereIn('concessioner_accounts.status', ['AB', 'ID', 'BL', 'IV'])
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [
                            $startDate . ' 00:00:00',
                            $endDate . ' 23:59:59',
                        ]);
                    })
                    ->select([
                        'concessioner_accounts.zone',
                        'readings.account_no',
                        'users.name',
                        'concessioner_accounts.status',
                        'concessioner_accounts.rate_code',
                        'bill.created_at as bill_date',
                        'bill.bill_period_to',
                        'bill.reference_no',
                        'readings.consumption',
                        'bill.total',
                        'bill.previous_unpaid',
                        'bill.discount',
                        'bill.amount',
                        'bill.high_consumption_note',
                        'concessioner_accounts.sequence_no',
                    ])
                    ->orderBy('concessioner_accounts.zone', 'asc')
                    ->orderBy('concessioner_accounts.sequence_no', 'asc')
                    ->orderBy('bill.bill_period_to', 'asc')
                    ->get();

                $headers = [
                    'Zone',
                    'Account No',
                    'Name',
                    'Status (AB, ID, BL, IV)',
                    'Rate',
                    'Bill Date',
                    'Bill No (reference_no)',
                    'Consumption',
                    'Basic',
                    'SC Discount',
                    'Ammount Billed',
                    'Arrears',
                    'Bill Mode',
                    'Remarks',
                ];

                $rowsByZone = [];

                foreach ($query as $row) {
                    $total = (float) ($row->total ?? 0);
                    $previousUnpaid = (float) ($row->previous_unpaid ?? 0);
                    $amount_billed = $total - $previousUnpaid;
                    $discount = (float) ($row->discount ?? 0);
                    $zoneKey = $row->zone ?? 'N/A';

                    $rowsByZone[$zoneKey][] = [
                        'Zone' => $row->zone ?? 'N/A',
                        'Account No' => $row->account_no ?? 'N/A',
                        'Name' => $row->name ?? 'N/A',
                        'Status (AB, ID, BL, IV)' => $row->status ?? 'N/A',
                        'Rate' => $row->rate_code ?? 'N/A',
                        'Bill Date' => $row->bill_date ? Carbon::parse($row->bill_date)->format('m/d/Y') : 'N/A',
                        'Bill No (reference_no)' => $row->reference_no ?? 'N/A',
                        'Consumption' => $row->consumption ?? 0,
                        'Basic' => $amount_billed ?? 0,
                        'SC Discount' => $discount ?? 0,
                        'Ammount Billed' => $amount_billed - $discount ?? 0,
                        'Arrears' => $previousUnpaid,
                        'Bill Mode' => 'Metered',
                        'Remarks' => $row->high_consumption_note ?? '',
                    ];
                }

                $spreadsheet = new Spreadsheet();

                $reportMonth = $startDate
                    ? Carbon::parse($startDate)->format('F Y')
                    : ($endDate ? Carbon::parse($endDate)->format('F Y') : now()->format('F Y'));

                if (empty($rowsByZone)) {
                    $rowsByZone = ['NO DATA' => []];
                }

                $firstSheet = true;

                foreach ($rowsByZone as $zoneKey => $zoneRows) {
                    if ($firstSheet) {
                        $sheet = $spreadsheet->getActiveSheet();
                        $firstSheet = false;
                    } else {
                        $sheet = $spreadsheet->createSheet();
                    }

                    $sheet->setTitle(substr('Zone ' . $zoneKey, 0, 31));
                    $sheet->setCellValue('A1', 'STA RITA WATER DISTRICT');
                    $sheet->setCellValue('A2', 'BILLING REPORT');
                    $sheet->setCellValue('A3', 'FOR THE MONTH OF ' . strtoupper($reportMonth));
                    $sheet->fromArray([$headers], null, 'A4');

                    if (!empty($zoneRows)) {
                        $this->writeRowsInChunks($sheet, $zoneRows, 5);
                    } else {
                        $sheet->setCellValue('A5', 'No data found');
                    }

                    foreach (['A1:N1', 'A2:N2', 'A3:N3'] as $range) {
                        $sheet->mergeCells($range);
                        $sheet->getStyle($range)->getAlignment()->setHorizontal('center');
                    }

                    $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                    $sheet->getStyle('A1')->getFont()->setSize(14);
                    $sheet->getStyle('A4:N4')->getFont()->setBold(true);
                    $sheet->getStyle('A4:N4')->getAlignment()
                        ->setHorizontal('center')
                        ->setVertical('center');

                    $sheet->getStyle('A4:N4')
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                    foreach (range('A', 'N') as $col) {
                        $sheet->getColumnDimension($col)->setWidth($col === 'N' ? 24 : 16);
                    }
                }

                $result[$report] = [
                    'type' => 'formatted',
                    'spreadsheet' => $spreadsheet,
                ];
                break;

                case 'Billed Con by Category and Size':
                $readings = Reading::with(['bill', 'concessionaire'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->orderBy('zone', 'asc')
                    ->get();

                if ($readings->isEmpty()) {
                    $result[$report] = [['Classification' => 'No data found']];
                    break;
                }

                $sizes = \App\Models\PropertyTypes::pluck('name')
                    ->map(function ($n) {
                        preg_match('/(\d+ ?\/? ?\d*)"?/', $n, $m);
                        return $m[1] ?? null;
                    })
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $columns = array_merge(['Classification / Zone'], $sizes, ['Total']);
                $rows = [];
                $zonesGrouped = $readings->groupBy('zone');
                $grandTotals = array_fill_keys($sizes, 0);
                $grandTotals['Total'] = 0;

                foreach ($zonesGrouped as $zoneKey => $zoneReadings) {
                    $rows[] = array_merge(['Classification / Zone' => "Zone $zoneKey"], array_fill_keys($sizes, ''), ['Total' => '']);
                    $byClassification = $zoneReadings->groupBy(function ($r) {
                        $type = $r->concessionaire->property_type ?? '';
                        return preg_replace('/\s+\d.*$/', '', $type); 
                    });

                    foreach ($byClassification as $classification => $classReadings) {
                        $counts = array_fill_keys($sizes, 0);

                        foreach ($classReadings as $r) {
                            $type = $r->concessionaire->property_type ?? '';
                            preg_match('/(\d+ ?\/? ?\d*)"?/', $type, $m);
                            $size = $m[1] ?? null;

                            if ($size && isset($counts[$size])) {
                                $counts[$size]++;
                            }
                        }

                        $rowTotal = array_sum($counts);
                        foreach ($counts as $size => $count) {
                            $grandTotals[$size] += $count;
                        }
                        $grandTotals['Total'] += $rowTotal;

                        $rows[] = array_merge(
                            ['Classification / Zone' => $classification],
                            $counts,
                            ['Total' => $rowTotal]
                        );
                    }

                    $rows[] = array_fill_keys($columns, '');
                }

                $rows[] = array_merge(['Classification' => 'GRAND TOTAL'], $grandTotals);

                $result[$report] = $rows;
                break;

                case 'Consumption by Category & Size':
                $readings = Reading::with(['bill', 'concessionaire'])
                    ->whereHas('bill')
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->orderBy('zone', 'asc')
                    ->get();

                if ($readings->isEmpty()) {
                    $result[$report] = [['Classification' => 'No data found']];
                    break;
                }

                $sizes = \App\Models\PropertyTypes::pluck('name')
                    ->map(function ($n) {
                        preg_match('/(\d+ ?\/? ?\d*)"?/', $n, $m);
                        return $m[1] ?? null;
                    })
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

                $columns = array_merge(['Classification / Zone'], $sizes, ['Total']);
                $rows = [];
                $zonesGrouped = $readings->groupBy('zone');
                $grandTotals = array_fill_keys($sizes, 0);
                $grandTotals['Total'] = 0;

                foreach ($zonesGrouped as $zoneKey => $zoneReadings) {
                    $rows[] = array_merge(['Classification / Zone' => "Zone $zoneKey"], array_fill_keys($sizes, ''), ['Total' => '']);

                    $byClassification = $zoneReadings->groupBy(function ($r) {
                        $type = $r->concessionaire->property_type ?? '';
                        return preg_replace('/\s+\d.*$/', '', $type);
                    });

                    foreach ($byClassification as $classification => $classReadings) {
                        $consumptionTotals = array_fill_keys($sizes, 0);

                        foreach ($classReadings as $r) {
                            $type = $r->concessionaire->property_type ?? '';
                            preg_match('/(\d+ ?\/? ?\d*)"?/', $type, $m);
                            $size = $m[1] ?? null;

                            if ($size && isset($consumptionTotals[$size])) {
                                $consumptionTotals[$size] += $r->consumption ?? 0;
                            }
                        }

                        $rowTotal = array_sum($consumptionTotals);
                        foreach ($consumptionTotals as $size => $value) {
                            $grandTotals[$size] += $value;
                        }
                        $grandTotals['Total'] += $rowTotal;

                        $rows[] = array_merge(
                            ['Classification / Zone' => $classification],
                            array_map(fn($v) => number_format($v, 2), $consumptionTotals),
                            ['Total' => number_format($rowTotal, 2)]
                        );
                    }

                    $rows[] = array_fill_keys($columns, '');
                }

                $rows[] = array_merge(
                    ['Classification / Zone' => 'GRAND TOTAL'],
                    array_map(fn($v) => number_format($v, 2), $grandTotals)
                );

                $result[$report] = $rows;
                break;

                case 'All Payments':

                $query = Bill::query()
                    ->with(['reading.concessionaire.user'])
                    ->whereNotNull('amount_paid') // only paid bills
                    ->when($zone !== 'all', function ($q) use ($zone) {
                        $q->whereHas('reading', fn($q2) => $q2->where('zone', $zone));
                    })
                    ->when($startDate, fn($q) => $q->whereDate('date_paid', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->whereDate('date_paid', '<=', $endDate))
                    ->orderBy('date_paid', 'asc')
                    ->get();

                $rows = [];

                foreach ($query as $bill) {
                    $reading = $bill->reading;

                    $rows[] = [
                        'ACCOUNT NO'    => $reading->account_no ?? 'N/A',
                        'ZONE'          => $reading->zone ?? 'N/A',
                        'CONCESSIONAIRE' => optional(optional($reading->concessionaire)->user)->name ?? 'N/A',
                        'REFERENCE NO'  => $bill->reference_no,
                        'BILL PERIOD'   => $bill->bill_period_from . ' - ' . $bill->bill_period_to,
                        'AMOUNT'        => $bill->amount,
                        'PENALTY'       => $bill->penalty,
                        'DISCOUNT'      => $bill->discount,
                        'TOTAL'         => $bill->total,
                        'IS PARTIAL'    => $bill->isPartial,
                        'PARTIAL PAYMENT'   => $bill->partial_payment,
                        'IS PAID'       => $bill->isPaid,
                        'AMOUNT PAID'   => $bill->amount_paid,
                        'PAYMENT METHOD'=> $bill->payment_method ?? 'N/A',
                        'DATE PAID'     => $bill->date_paid,
                    ];
                }

                $result[$report] = $rows;
                break;

                case 'Online Payments':

                $query = Bill::query()
                    ->with(['reading.concessionaire.user'])
                    ->whereNotNull('amount_paid')
                    ->where('payment_method', 'online')
                    ->when($zone !== 'all', function ($q) use ($zone) {
                        $q->whereHas('reading', function ($q2) use ($zone) {
                            $q2->where('zone', $zone);
                        });
                    })
                    ->when($startDate, function ($q) use ($startDate) {
                        $q->whereDate('date_paid', '>=', $startDate);
                    })
                    ->when($endDate, function ($q) use ($endDate) {
                        $q->whereDate('date_paid', '<=', $endDate);
                    })
                    ->orderBy('date_paid', 'asc')
                    ->get();

                $rows = [];

                foreach ($query as $bill) {
                    $reading = $bill->reading;

                    $rows[] = [
                        'ACCOUNT NO'      => $reading->account_no ?? 'N/A',
                        'ZONE'            => $reading->zone ?? 'N/A',
                        'CONCESSIONAIRE'  => optional(
                            optional($reading->concessionaire)->user
                        )->name ?? 'N/A',
                        'REFERENCE NO'    => $bill->reference_no,
                        'BILL PERIOD'     => $bill->bill_period_from . ' - ' . $bill->bill_period_to,
                        'AMOUNT'          => $bill->date_paid > $bill->due_date ? $bill->amount : $bill->total,
                        'AMOUNT PAID'     => $bill->amount_paid,
                        'PAYMENT METHOD'  => $bill->payment_method ?? 'N/A',
                        'DATE PAID'       => $bill->date_paid,
                    ];
                }

                $result[$report] = $rows;

                break;

                case 'Unpaid Bills':

                $selectedStart = $startDate
                    ? Carbon::parse($startDate)->startOfDay()
                    : ($endDate ? Carbon::parse($endDate)->startOfMonth() : now()->startOfMonth());

                $selectedEnd = $endDate
                    ? Carbon::parse($endDate)->endOfDay()
                    : now()->endOfDay();

                $accountsWithSelectedMonthBill = Bill::query()
                    ->join('readings', 'readings.id', '=', 'bill.reading_id')
                    ->when($zone !== 'all', function ($q) use ($zone) {
                        $q->where('readings.zone', $zone);
                    })
                    ->whereBetween('bill.bill_period_to', [$selectedStart, $selectedEnd])
                    ->pluck('readings.account_no')
                    ->unique()
                    ->values();

                $query = Bill::query()
                ->join('readings', 'readings.id', '=', 'bill.reading_id')
                ->joinSub(
                    DB::table('concessioner_accounts')
                        ->select('account_no')
                        ->selectRaw('MIN(sequence_no) as sequence_no')
                        ->selectRaw('MIN(zone) as zone')
                        ->groupBy('account_no'),
                    'concessioner_accounts',
                    'concessioner_accounts.account_no',
                    '=',
                    'readings.account_no'
                )
                ->with(['reading.concessionaire.user'])
                ->when($zone !== 'all', function ($q) use ($zone) {
                    $q->where('readings.zone', $zone);
                })
                ->where('bill.bill_period_to', '<=', $selectedEnd)
                ->where(function ($q) use ($selectedEnd) {
                    $q->whereNull('bill.date_paid')
                        ->orWhere('bill.date_paid', '>', $selectedEnd)
                        ->orWhere('bill.partial_payment', '>', 0)
                        ->orWhere('bill.previous_unpaid', '>', 0);
                })
                ->orderBy('readings.zone')
                ->orderBy('concessioner_accounts.sequence_no')
                ->select('bill.*', 'concessioner_accounts.sequence_no', 'readings.zone as report_zone')
                ->get();

                $query = $query
                    ->groupBy(fn ($bill) => optional($bill->reading)->account_no)
                    ->flatMap(function ($accountBills, $accountNo) use ($selectedStart, $selectedEnd, $accountsWithSelectedMonthBill) {
                        $selectedMonthBills = $accountBills->filter(function ($bill) use ($selectedStart, $selectedEnd) {
                            return Carbon::parse($bill->bill_period_to)->between($selectedStart, $selectedEnd);
                        });

                        return $accountsWithSelectedMonthBill->contains($accountNo)
                            ? $selectedMonthBills
                            : $accountBills
                                ->sortByDesc(fn ($bill) => Carbon::parse($bill->bill_period_to)->timestamp)
                                ->take(1);
                    })
                    ->sortBy([
                        ['report_zone', 'asc'],
                        ['sequence_no', 'asc'],
                        ['bill_period_to', 'asc'],
                    ])
                    ->values();

                $rows = [];

                foreach ($query as $bill) {
                    $reading = $bill->reading;

                    $rows[] = [
                        'ACCOUNT NO'            => $reading->account_no ?? 'N/A',
                        'CONCESSIONAIRE'        => optional(optional($reading->concessionaire)->user)->name ?? 'N/A',
                        'PREVIOUS CONSUMPTION'  => $reading->previous_reading ?? 0,
                        'BASIC CHARGE'          => $bill->total - $bill->previous_unpaid ?? 0,
                        'TOTAL'                 => $bill->total ?? 0,
                        'DISCOUNT'              => $bill->discount ?? 0,
                        'PENALTY'               => $bill->penalty ?? 0,
                        'AMOUNT AFTER DUE'      => $bill->amount ?? 0,
                    ];
                }

                $result[$report] = $rows;
                break;

                case 'Paid Bills':

                $query = Bill::query()
                    ->join('readings', 'readings.id', '=', 'bill.reading_id')
                    ->join(
                        'concessioner_accounts',
                        'concessioner_accounts.account_no',
                        '=',
                        'readings.account_no'
                    )
                    ->with(['reading.concessionaire.user'])
                    ->where('bill.isPaid', 1)
                    ->whereNotNull('bill.amount_paid')
                    ->when($zone !== 'all', function ($q) use ($zone) {
                        $q->where('concessioner_accounts.zone', $zone);
                    })
                    ->when($startDate, fn ($q) => $q->whereDate('bill.created_at', '>=', $startDate))
                    ->when($endDate, fn ($q) => $q->whereDate('bill.created_at', '<=', $endDate))
                    ->orderBy('concessioner_accounts.sequence_no', 'asc')
                    ->orderBy('bill.created_at', 'asc')
                    ->select('bill.*', 'concessioner_accounts.sequence_no as sequence_no')
                    ->get();

                $rows = [];

                foreach ($query as $bill) {
                    $reading = $bill->reading;

                    $rows[] = [
                        'REFERENCE NO'          => $bill->reference_no ?? 'N/A',
                        'ACCOUNT NO'            => $reading->account_no ?? 'N/A',
                        'CONCESSIONAIRE'        => optional(optional($reading->concessionaire)->user)->name ?? 'N/A',
                        'PREVIOUS CONSUMPTION'  => $reading->previous_reading ?? 0,
                        'CURRENT CONSUMPTION'   => $reading->present_reading ?? 0,
                        'CONSUMPTION'           => $reading->consumption ?? 0,
                        'PREVIOUS UNPAID'       => $bill->previous_unpaid ?? 0,
                        'BASIC CHARGE'          => $bill->total - $bill->previous_unpaid ?? 0,
                        'TOTAL'                 => $bill->total ?? 0,
                        'DISCOUNT'              => $bill->discount ?? 0,
                        'PENALTY'               => $bill->penalty ?? 0,
                        'AMOUNT AFTER DUE'      => $bill->amount ?? 0,
                        'AMOUNT PAID'           => $bill->amount_paid?? 0,
                        'SEQUENCE NO'           => $bill->sequence_no ?? 0,
                    ];
                }

                $result[$report] = $rows;
                break;

                case 'Readings (90days)':

                    $anchorDate = $endDate
                        ? Carbon::parse($endDate)
                        : ($startDate ? Carbon::parse($startDate) : now());

                    $months = collect(range(0, 3))
                        ->map(fn ($offset) => $anchorDate->copy()->subMonths($offset))
                        ->values();

                    $monthStart = $months->last()->copy()->startOfMonth();
                    $monthEnd = $months->first()->copy()->endOfMonth();

                    $monthSelects = $months->map(function ($month, $index) {
                        $start = $month->copy()->startOfMonth()->format('Y-m-d H:i:s');
                        $end = $month->copy()->endOfMonth()->format('Y-m-d H:i:s');
                        $alias = 'month_' . $index . '_amount';

                        return "SUM(CASE WHEN bill.bill_period_to BETWEEN '{$start}' AND '{$end}' THEN CAST(bill.amount AS DECIMAL(12,2)) ELSE 0 END) AS {$alias}";
                    })->implode(",\n                            ");

                    $query = Bill::query()
                        ->join('readings', 'readings.id', '=', 'bill.reading_id')
                        ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                        ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')

                        ->where('bill.isPaid', 0)
                        ->whereIn('concessioner_accounts.status', ['AB', 'ID', 'BL', 'IV'])
                        ->whereBetween('bill.bill_period_to', [
                            $monthStart->format('Y-m-d H:i:s'),
                            $monthEnd->format('Y-m-d H:i:s'),
                        ])

                        ->when($zone !== 'all', fn ($q) =>
                            $q->where('concessioner_accounts.zone', $zone)
                        )

                        ->selectRaw("
                            concessioner_accounts.zone AS zone,
                            readings.account_no,
                            users.name AS concessionaire_name,

                            MAX(concessioner_accounts.sequence_no) AS sequence_no,
                            MAX(CAST(readings.present_reading AS UNSIGNED)) AS current_reading,

                            {$monthSelects},

                            SUM(CAST(readings.consumption AS UNSIGNED)) AS total_consumption,

                            SUM(CAST(bill.amount AS DECIMAL(12,2))) AS total_amount,
                            SUM(CAST(bill.previous_unpaid AS DECIMAL(12,2))) AS arrears,
                            SUM(CAST(bill.discount AS DECIMAL(12,2))) AS discount,

                            MAX(concessioner_accounts.status) AS status,
                            MAX(concessioner_accounts.rate_code) AS rate_code,
                            SUM(CAST(bill.amount AS DECIMAL(12,2))) AS amount
                        ")

                        ->groupBy(
                            'concessioner_accounts.zone',
                            'readings.account_no',
                            'users.name'
                        )

                        ->orderBy('concessioner_accounts.zone', 'ASC')
                        ->orderBy('sequence_no', 'ASC')

                        ->get();

                    $result = [];

                    foreach ($query as $row) {

                        $sheetName = 'ZONE ' . $row->zone;
                        $monthAmounts = [];

                        foreach ($months as $index => $month) {
                            $monthAmounts[strtoupper($month->format('F'))] = $row->{'month_' . $index . '_amount'} ?? 0;
                        }

                        $result[$sheetName][] = array_merge([
                            'ACCOUNT NUMBER'      => $row->account_no,
                            'NAME'                => $row->concessionaire_name ?? 'N/A',
                            'STATUS'              => $row->status ?? 'N/A',
                            'RATE CODE'           => $row->rate_code ?? 'N/A',
                            'CURRENT READING'     => $row->current_reading ?? 0,
                        ], $monthAmounts, [
                            'TOTAL CONSUMPTION'   => $row->total_consumption ?? 0,
                            'TOTAL AMOUNT'        => $row->total_amount ?? 0,
                            'ARREARS'             => $row->arrears ?? 0,
                            'DISCOUNT'            => $row->discount ?? 0,
                            'TOTAL DUE'           => $row->amount ?? 0,
                        ]);
                    }

                    break;

                    case 'Readings (Detailed)':

                    $query = Bill::query()
                        ->join('readings', 'readings.id', '=', 'bill.reading_id')
                        ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                        ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')

                        ->when($zone !== 'all', fn ($q) =>
                            $q->where('concessioner_accounts.zone', $zone)
                        )

                        ->when($startDate && $endDate, fn ($q) =>
                            $q->whereBetween('bill.bill_period_to', [$startDate, $endDate])
                        )

                        ->select([
                            'bill.reference_no',
                            'readings.account_no',
                            'users.name as concessionaire_name',
                            'readings.previous_reading',
                            'readings.present_reading as current_reading',
                            'readings.consumption',
                            'concessioner_accounts.sequence_no',
                            'concessioner_accounts.zone',
                        ])

                        ->orderBy('concessioner_accounts.zone', 'ASC')
                        ->orderBy('concessioner_accounts.sequence_no', 'ASC')
                        ->get();

                    $result = [];

                    foreach ($query as $row) {
                        $sheetName = 'ZONE ' . $row->zone;

                        $result[$sheetName][] = [
                            'REFERENCE NO'     => $row->reference_no,
                            'ACCOUNT NO'       => $row->account_no,
                            'CONCESSIONER'     => $row->concessionaire_name,
                            'PREVIOUS READING' => (int) $row->previous_reading,
                            'CURRENT READING'  => (int) $row->current_reading,
                            'CONSUMPTION'      => (int) $row->consumption,
                            'SEQUENCE NO'      => $row->sequence_no,
                        ];
                    }

                    break;

                    case 'Senior Count':

                $query = DB::table('discount')
                    ->join(
                        'concessioner_accounts',
                        'concessioner_accounts.account_no',
                        '=',
                        'discount.account_no'
                    )
                    ->leftJoin('readings', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                    ->leftJoin('bill', 'bill.reading_id', '=', 'readings.id')
                    ->where('discount.discount_type_id', 1) // Senior Citizen
                    ->whereNotNull('concessioner_accounts.zone')
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                    })
                    ->groupBy('concessioner_accounts.zone')
                    ->select(
                        'concessioner_accounts.zone as zone',
                        DB::raw('COUNT(DISTINCT discount.account_no) as senior_count'),
                        DB::raw('COALESCE(SUM(bill.amount), 0) as total_amount')
                    )
                    ->orderBy('concessioner_accounts.zone', 'asc')
                    ->get();

                $rows = [];

                foreach ($query as $row) {
                    $rows[] = [
                        'ZONE'           => $row->zone ?? 'N/A',
                        'NO. OF SENIORS' => $row->senior_count,
                        'TOTAL AMOUNT'  => number_format($row->total_amount ?? 0, 2),
                    ];
                }

                $result[$report] = $rows;
                break;

                case 'List of Concessionaires':
                    $accounts = ConcessionerAccount::query()
                        ->with(['user:id,name'])
                        ->when($zone !== 'all', fn ($query) => $query->where('zone', $zone))
                        ->orderBy('zone')
                        ->orderBy('sequence_no')
                        ->get(['account_no', 'user_id', 'address', 'status']);

                    $result[$report] = ['CONCESSIONAIRES' => $accounts->map(fn ($account) => [
                        'Account Number' => $account->account_no,
                        'Name' => $account->user->name ?? 'N/A',
                        'Address' => $account->address ?? 'N/A',
                        'Status' => match ($account->status) {'AB' => 'Active', 'ID', 'IV', 'BL' => 'Inactive', default => 'N/A',},
                    ])->all()];
                break;

                case 'List of Active':

                $query = Bill::query()
                ->join('readings', 'readings.id', '=', 'bill.reading_id')
                ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')

                ->where('bill.isPaid', 0)
                ->where('concessioner_accounts.status', 'AB')

                ->when($zone !== 'all', fn ($q) =>
                    $q->where('concessioner_accounts.zone', $zone)
                )

                ->select(
                    'concessioner_accounts.zone',
                    'concessioner_accounts.status',
                    'concessioner_accounts.sequence_no',
                    'readings.account_no',
                    'users.name as customer_name'
                )

                ->distinct()

                ->orderBy('concessioner_accounts.zone', 'ASC')
                ->orderBy('concessioner_accounts.sequence_no', 'ASC')

                ->get();

                $result[$report] = [
                    'ACTIVE CONCESSIONAIRES'   => [],
                    'INACTIVE CONCESSIONAIRES' => [],
                ];

                $zoneCounter = [
                    'ACTIVE CONCESSIONAIRES'   => [],
                    'INACTIVE CONCESSIONAIRES' => [],
                ];

                foreach ($query as $row) {

                    $sheet = ($row->status === 'AB')
                        ? 'ACTIVE CONCESSIONAIRES'
                        : 'INACTIVE CONCESSIONAIRES';

                    if (!isset($zoneCounter[$sheet][$row->zone])) {

                        $zoneCounter[$sheet][$row->zone] = 1;

                        $result[$report][$sheet][] = [
                            'NO.'           => "ZONE {$row->zone}",
                            'ACCOUNT NO.'   => '',
                            'CUSTOMER NAME' => '',
                            'FEB'           => '',
                            'JAN'           => '',
                            'DEC'           => '',
                            'NOV'           => '',
                            'OCT & OVER'    => '',
                            'TOTAL'         => '',
                            'OVER PAYT'     => '',
                        ];
                    }

                    $result[$report][$sheet][] = [
                        'NO.'           => $zoneCounter[$sheet][$row->zone]++,
                        'ACCOUNT NO.'   => $row->account_no,
                        'CUSTOMER NAME' => $row->customer_name ?? 'N/A',
                        'FEB'           => '',
                        'JAN'           => '',
                        'DEC'           => '',
                        'NOV'           => '',
                        'OCT & OVER'    => '',
                        'TOTAL'         => '',
                        'OVER PAYT'     => '',
                    ];
                }

                break;

                case 'List of Inactive':

                $inactiveCutoff = $endDate
                    ? Carbon::parse($endDate)->endOfDay()
                    : ($startDate ? Carbon::parse($startDate)->endOfMonth() : now()->endOfDay());

                $data = Bill::query()
                    ->join('readings', 'readings.id', '=', 'bill.reading_id')
                    ->join('concessioner_accounts', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                    ->leftJoin('users', 'users.id', '=', 'concessioner_accounts.user_id')
                    ->whereIn('concessioner_accounts.status', ['ID', 'IV', 'BL'])
                    ->where('bill.isPaid', 0)
                    ->where('bill.amount', '>', 0)
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->where('bill.bill_period_to', '<=', $inactiveCutoff->format('Y-m-d H:i:s'))
                    ->selectRaw("
                        concessioner_accounts.zone AS zone,
                        concessioner_accounts.sequence_no AS sequence_no,
                        concessioner_accounts.account_no AS account_no,
                        users.name AS customer_name,
                        concessioner_accounts.status AS status,
                        COALESCE(SUM(CAST(bill.amount AS DECIMAL(12,2))), 0) AS balance
                    ")
                    ->groupBy(
                        'concessioner_accounts.zone',
                        'concessioner_accounts.sequence_no',
                        'concessioner_accounts.account_no',
                        'users.name',
                        'concessioner_accounts.status'
                    )
                    ->orderBy('concessioner_accounts.zone')
                    ->orderBy('concessioner_accounts.sequence_no')
                    ->get();

                $result[$report] = [];
                $zoneCounters = [];
                $zoneTotals = [];

                foreach ($data as $row) {
                    $sheetName = 'ZONE ' . ($row->zone ?? 'N/A');
                    $zoneCounters[$sheetName] = ($zoneCounters[$sheetName] ?? 0) + 1;
                    $zoneTotals[$sheetName] = ($zoneTotals[$sheetName] ?? 0) + (float) ($row->balance ?? 0);

                    $result[$report][$sheetName][] = [
                        'NO.' => $zoneCounters[$sheetName],
                        'ACCOUNT NO.' => $row->account_no ?? 'N/A',
                        'CUSTOMER NAME' => $row->customer_name ?? 'N/A',
                        'STATUS' => $row->status ?? 'N/A',
                        'BALANCE' => $row->balance ?? 0,
                    ];
                }

                foreach ($zoneTotals as $sheetName => $total) {
                    $result[$report][$sheetName][] = [
                        'NO.' => '',
                        'ACCOUNT NO.' => '',
                        'CUSTOMER NAME' => 'TOTAL',
                        'STATUS' => '',
                        'BALANCE' => $total,
                    ];
                }

                if (empty($result[$report])) {
                    $result[$report] = [
                        'NO DATA' => [[
                            'NO.' => '',
                            'ACCOUNT NO.' => '',
                            'CUSTOMER NAME' => 'No data found',
                            'STATUS' => '',
                            'BALANCE' => 0,
                        ]],
                    ];
                }
                break;


                case 'Book Summary Report':

                $bookList = ConcessionerAccount::query()
                    ->select('zone')
                    ->distinct()
                    ->orderBy('zone')
                    ->pluck('zone')
                    ->map(fn ($z) => "ZONE {$z}")
                    ->values();

                $categories = [
                    'RESIDENTIAL',
                    'GOVERNMENT',
                    'COMMERCIAL & INDUSTRIAL',
                    'COMMERCIAL A',
                    'COMMERCIAL B',
                    'COMMERCIAL C',
                ];

                $barangays = [
                    'SAN BASILIO',
                    'BECURAN',
                    'DILA-DILA',
                    'SAN MATIAS',
                    'SAN VICENTE',
                ];
                $data = ConcessionerAccount::query()
                    ->leftJoin('readings', 'readings.account_no', '=', 'concessioner_accounts.account_no')
                    ->leftJoin('bill', function ($join) use ($startDate, $endDate) {
                        $join->on('bill.reading_id', '=', 'readings.id')
                            ->where('bill.isPaid', 0); // unpaid only
                        if ($startDate && $endDate) {
                            $join->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                        }
                    })
                    ->whereIn('concessioner_accounts.status', ['ID', 'IV', 'BL'])
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->selectRaw("
                        concessioner_accounts.zone AS zone,
                        COUNT(DISTINCT concessioner_accounts.account_no) AS total_inactive,
                        COALESCE(SUM(CAST(bill.amount AS DECIMAL(12,2))), 0) AS total_unpaid_amount
                    ")
                    ->groupBy('concessioner_accounts.zone')
                    ->orderBy('concessioner_accounts.zone')
                    ->get();

                $rows = [];

                foreach ($data as $row) {
                    $rows[] = [
                        'ZONE'         => 'ZONE ' . $row->zone,
                        'TOTAL'        => $row->total_inactive,
                        'TOTAL AMOUNT' => $row->total_unpaid_amount,
                    ];
                }

                $result[$report] = $rows;
                break;

                case 'Book Summary Report':

                $bookList = ConcessionerAccount::query()
                    ->select('zone')
                    ->distinct()
                    ->orderBy('zone')
                    ->pluck('zone')
                    ->map(fn ($z) => "ZONE {$z}")
                    ->values();

                $categories = [
                    'RESIDENTIAL',
                    'GOVERNMENT',
                    'COMMERCIAL & INDUSTRIAL',
                    'COMMERCIAL A',
                    'COMMERCIAL B',
                    'COMMERCIAL C',
                ];

                $barangays = [
                    'SAN BASILIO',
                    'BECURAN',
                    'DILA-DILA',
                    'SAN MATIAS',
                    'SAN VICENTE',
                ];

                $data = Bill::query()
                    ->join('readings', 'bill.reading_id', '=', 'readings.id')
                    ->join('concessioner_accounts', 'readings.account_no', '=', 'concessioner_accounts.account_no')
                    ->join('property_types', 'concessioner_accounts.rate_code', '=', 'property_types.rate_code')
                    ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                    })
                    ->selectRaw("
                        concessioner_accounts.zone AS zone,
                        CASE
                            WHEN property_types.rate_code = 12 THEN 'RESIDENTIAL'
                            WHEN property_types.rate_code = 22 THEN 'GOVERNMENT'
                            WHEN property_types.rate_code = 32 THEN 'COMMERCIAL & INDUSTRIAL'
                            WHEN property_types.rate_code = 62 THEN 'COMMERCIAL A'
                            WHEN property_types.rate_code = 52 THEN 'COMMERCIAL B'
                            WHEN property_types.rate_code = 42 THEN 'COMMERCIAL C'
                        END AS category,
                        COUNT(DISTINCT concessioner_accounts.account_no) AS cnt,
                        SUM(readings.consumption) AS cum,
                        SUM(COALESCE(bill.total - bill.previous_unpaid, 0)) AS amt,
                        SUM(COALESCE(bill.penalty, 0)) AS penalty
                    ")
                    ->groupBy('concessioner_accounts.zone', 'category')
                    ->orderBy('concessioner_accounts.zone')
                    ->get();

                $books = [];

                foreach ($bookList as $book) {
                    $books[$book] = [
                        'ZONE' => $book,
                        'TOTAL AMOUNT PER BOOK' => 0,
                        'TOTAL PENALTY' => 0,
                    ];

                    foreach ($categories as $cat) {
                        $books[$book]["{$cat} - NO. OF CONCESSIONAIRES"] = 0;
                        $books[$book]["{$cat} - CUBIC METER"] = 0;
                        $books[$book]["{$cat} - AMOUNT"] = 0;
                    }
                }

                foreach ($data as $row) {
                    $key = "ZONE {$row->zone}";

                    if (!isset($books[$key])) continue;

                    $books[$key]["{$row->category} - NO. OF CONCESSIONAIRES"] += $row->cnt;
                    $books[$key]["{$row->category} - CUBIC METER"] += $row->cum;
                    $books[$key]["{$row->category} - AMOUNT"] += $row->amt;

                    $books[$key]['TOTAL AMOUNT PER BOOK'] += $row->amt;
                    $books[$key]['TOTAL PENALTY'] += $row->penalty;
                }

                $zoneSummary = Bill::query()
                    ->join('readings', 'bill.reading_id', '=', 'readings.id')
                    ->join('concessioner_accounts', 'readings.account_no', '=', 'concessioner_accounts.account_no')
                    ->selectRaw('
                        concessioner_accounts.zone AS zone,
                        COUNT(DISTINCT concessioner_accounts.account_no) AS cnt,
                        SUM(COALESCE(bill.total - bill.previous_unpaid, 0)) AS total_paid,
                        SUM(COALESCE(bill.penalty, 0)) AS total_penalty
                    ')
                    ->where(function ($q) {
                        $q->where('bill.hasPenalty', 1);
                    })
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                    })
                    ->groupBy('concessioner_accounts.zone')
                    ->orderBy('concessioner_accounts.zone')
                    ->get();

                $seniorPenaltyPerZone = Bill::query()
                    ->join('readings', 'bill.reading_id', '=', 'readings.id')
                    ->join('concessioner_accounts', 'readings.account_no', '=', 'concessioner_accounts.account_no')
                    ->join('discount', function($join) {
                        $join->on('discount.account_no', '=', 'concessioner_accounts.account_no')
                            ->where('discount.discount_type_id', 1);
                    })
                    ->where('bill.penalty', '>', 0)           
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                        $q->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                    })
                    ->selectRaw('
                        concessioner_accounts.zone AS zone,
                        COUNT(DISTINCT concessioner_accounts.account_no) AS cnt,
                        SUM(bill.penalty) AS total_penalty
                    ')
                    ->groupBy('concessioner_accounts.zone')
                    ->orderBy('concessioner_accounts.zone')
                    ->get();

                $seniorDiscountPerZone = DB::table('discount')
                    ->join('concessioner_accounts', 'discount.account_no', '=', 'concessioner_accounts.account_no')
                    ->leftJoin('readings', 'concessioner_accounts.account_no', '=', 'readings.account_no')
                    ->leftJoin('bill', function($join) use ($startDate, $endDate) {
                        $join->on('bill.reading_id', '=', 'readings.id');
                        if ($startDate && $endDate) {
                            $join->whereBetween('bill.bill_period_to', [$startDate, $endDate]);
                        }
                    })
                    ->where('discount.discount_type_id', 1)
                    ->whereNotNull('concessioner_accounts.zone')
                    ->when($zone !== 'all', fn($q) => $q->where('concessioner_accounts.zone', $zone))
                    ->selectRaw('
                        concessioner_accounts.zone AS zone,
                        COUNT(DISTINCT discount.account_no) AS cnt,
                        COALESCE(SUM(CAST(bill.discount AS DECIMAL(10,2))), 0) AS total_discount
                    ')
                    ->groupBy('concessioner_accounts.zone')
                    ->orderBy('concessioner_accounts.zone')
                    ->get();

                $activePerProperty = \DB::table(function($query) use ($startDate, $endDate) {
                    $query->from('bill')
                        ->join('readings', 'bill.reading_id', '=', 'readings.id')
                        ->join('concessioner_accounts', 'readings.account_no', '=', 'concessioner_accounts.account_no')
                        ->where('concessioner_accounts.status', 'AB')
                        ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                            $q->whereBetween('readings.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                        })
                        ->selectRaw('
                            concessioner_accounts.account_no,
                            concessioner_accounts.rate_code,
                            SUM(readings.consumption) AS account_consumption,
                            SUM(COALESCE(bill.total - bill.previous_unpaid, 0)) AS account_amount
                        ')
                        ->groupBy('concessioner_accounts.account_no', 'concessioner_accounts.rate_code');
                }, 'account_summary')
                ->join('property_types as pt', 'account_summary.rate_code', '=', 'pt.rate_code')
                ->selectRaw("
                    CASE
                        WHEN pt.rate_code = 12 THEN 'RESIDENTIAL'
                        WHEN pt.rate_code = 22 THEN 'GOVERNMENT'
                        WHEN pt.rate_code = 32 THEN 'COMMERCIAL & INDUSTRIAL'
                        WHEN pt.rate_code = 62 THEN 'COMMERCIAL A'
                        WHEN pt.rate_code = 52 THEN 'COMMERCIAL B'
                        WHEN pt.rate_code = 42 THEN 'COMMERCIAL C'
                    END AS property_type,
                    COUNT(DISTINCT account_summary.account_no) AS cnt,
                    SUM(account_consumption) AS cum,
                    SUM(account_amount) AS amt
                ")
                ->groupBy('pt.rate_code')
                ->get();

                $totalArrearsPerZone = \DB::table(function ($query) use ($endDate) {
                    $query->from('bill as b')
                        ->join('readings as r', 'b.reading_id', '=', 'r.id')
                        ->join('concessioner_accounts as c', 'c.account_no', '=', 'r.account_no')
                        ->where('b.bill_period_to', '<=', $endDate)
                        ->whereIn('c.status', ['ID', 'IV', 'BL'])
                        ->whereRaw('CAST(COALESCE(b.previous_unpaid, 0) AS DECIMAL(10,2)) > 0')
                        ->selectRaw('
                            r.account_no,
                            MAX(b.bill_period_to) AS last_period
                        ')
                        ->groupBy('r.account_no');
                }, 'last_bill')
                ->join('readings as r', 'r.account_no', '=', 'last_bill.account_no')
                ->join('bill as b', function ($join) {
                    $join->on('b.reading_id', '=', 'r.id')
                        ->on('b.bill_period_to', '=', 'last_bill.last_period');
                })
                ->join('concessioner_accounts as c', 'c.account_no', '=', 'r.account_no')
                ->selectRaw('
                    c.zone AS zone,
                    COUNT(DISTINCT c.account_no) AS cnt,
                    SUM(CAST(b.previous_unpaid AS DECIMAL(10,2))) AS total_arrears
                ')
                ->groupBy('c.zone')
                ->orderBy('c.zone')
                ->get();


                $inactiveCountsPerZone = \DB::table('concessioner_accounts')
                    ->whereIn('status', ['ID', 'IV', 'BL'])
                    ->selectRaw('
                        zone,
                        COUNT(DISTINCT account_no) AS inactive_cnt
                    ')
                    ->groupBy('zone')
                    ->pluck('inactive_cnt', 'zone');

                $disconnectedPerZone = ConcessionerAccount::query()
                    ->whereIn('status', ['IV', 'ID', 'BL'])
                    ->groupBy('zone')
                    ->selectRaw('zone, COUNT(DISTINCT account_no) AS disconnected_cnt')
                    ->pluck('disconnected_cnt', 'zone');

                $activePerBarangay = [];

                foreach ($barangays as $brgy) {
                    $activePerBarangay[$brgy] = ConcessionerAccount::query()
                        ->where('status', 'AB')
                        ->where('address', 'LIKE', "%{$brgy}%")
                        ->count();
                }

                $disconnected = ConcessionerAccount::whereIn('status', [3, 5])->count();

                $rows = array_values($books);
                $rows[] = [
                    'BOOK' => '--- PENALTY PER ZONE ---',
                    'NO. OF CONCESSIONAIRES' => null,
                    'DISCONNECTED' => null,
                    'TOTAL AMOUNT' => null,
                ];
                foreach ($zoneSummary as $row) {
                    $rows[] = [
                        'BOOK' => "ZONE {$row->zone}",
                        'NO. OF CONCESSIONAIRES' => $row->cnt,
                        'DISCONNECTED' => $disconnectedPerZone[$row->zone] ?? 0,
                        'TOTAL AMOUNT' => $row->total_penalty,
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- TOTAL ARREARS (INACTIVE ACCOUNTS ONLY) ---',
                    'INACTIVE CONCESSIONAIRES' => null,
                    'WITH ARREARS' => null,
                    'TOTAL AMOUNT' => null,
                ];

                foreach ($totalArrearsPerZone as $row) {
                    $rows[] = [
                        'BOOK' => "ZONE {$row->zone}",
                        'INACTIVE CONCESSIONAIRES' => $inactiveCountsPerZone[$row->zone] ?? 0,
                        'WITH ARREARS' => $row->cnt,
                        'TOTAL AMOUNT' => number_format($row->total_arrears, 2),
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- SENIOR DISCOUNT PER ZONE ---',
                    'NO. OF CONCESSIONAIRES' => null,
                    'TOTAL AMOUNT' => null,
                ];

                foreach ($seniorDiscountPerZone as $row) {
                    $rows[] = [
                        'BOOK' => "ZONE {$row->zone}",
                        'NO. OF CONCESSIONAIRES' => $row->cnt,
                        'TOTAL AMOUNT' => $row->total_discount,
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- SENIOR CITIZEN PENALTY PER ZONE ---',
                    'NO. OF CONCESSIONAIRES' => null,
                    'TOTAL AMOUNT' => null,
                ];

                foreach ($seniorPenaltyPerZone as $row) {
                    $rows[] = [
                        'BOOK' => "ZONE {$row->zone}",
                        'NO. OF CONCESSIONAIRES' => $row->cnt,
                        'TOTAL AMOUNT' => $row->total_penalty,
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- ACTIVE PER PROPERTY TYPE ---',
                    'NO. OF CONCESSIONAIRES' => null,
                    'CUBIC METER' => null,
                    'TOTAL AMOUNT' => null,
                ];

                foreach ($activePerProperty as $row) {
                    $rows[] = [
                        'BOOK' => $row->property_type,
                        'NO. OF CONCESSIONAIRES' => $row->cnt,
                        'CUBIC METER' => $row->cum,
                        'TOTAL AMOUNT' => $row->amt,
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- ACTIVE PER BARANGAY ---',
                    'NO. OF CONCESSIONAIRES' => null,
                ];

                foreach ($activePerBarangay as $brgy => $count) {
                    $rows[] = [
                        'BOOK' => $brgy,
                        'NO. OF CONCESSIONAIRES' => $count,
                    ];
                }

                $rows[] = [
                    'BOOK' => '--- TOTAL DISCONNECTED ---',
                    'NO. OF CONCESSIONAIRES' => $disconnected,
                ];

                $result[$report] = $rows;
                break;

                case 'Monthly Billing Matrix Report':

                $zones = \App\Models\Zones::orderBy('zone')->pluck('zone');

                $readings = Reading::with(['concessionaire', 'bill'])
                    ->when($zone !== 'all', fn($q) => $q->where('zone', $zone))
                    ->where('isReread', 0)
                    ->whereHas('bill', function ($q) use ($startDate, $endDate) {
                        if ($startDate && $endDate) {
                            $q->whereBetween('bill.bill_period_to', [
                                $startDate . ' 00:00:00',
                                $endDate . ' 23:59:59'
                            ]);
                        }
                    })
                    ->get();

                $matrix = [];

                foreach ($readings as $reading) {

                    $bill = $reading->bill;
                    if (!$bill) continue;
                    $zoneKey = $reading->zone;
                    $type = strtoupper(trim(optional($reading->concessionaire)->property_type ?? ''));

                    if (str_contains($type, 'RESIDENTIAL')) $cat = 'RES';
                    elseif (str_contains($type, 'GOVERNMENT')) $cat = 'GOV';
                    elseif (str_contains($type, 'COMMERCIAL') && str_contains($type, 'INDUSTRIAL')) $cat = 'CI';
                    elseif (str_contains($type, 'COMMERCIAL A')) $cat = 'A';
                    elseif (str_contains($type, 'COMMERCIAL B')) $cat = 'B';
                    elseif (str_contains($type, 'COMMERCIAL C')) $cat = 'C';
                    else continue;

                    if (!isset($matrix[$zoneKey][$cat])) {
                        $matrix[$zoneKey][$cat] = ['count'=>0,'cum'=>0,'amt'=>0];
                    }

                    $matrix[$zoneKey][$cat]['count']++;
                    $matrix[$zoneKey][$cat]['cum'] += (float) ($reading->consumption ?? 0);
                    $matrix[$zoneKey][$cat]['amt'] += (float) ($bill->total - $bill->previous_unpaid ?? 0);
                }

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setCellValue('A1', strtoupper(Carbon::parse($startDate)->format('F Y')));
                $sheet->mergeCells('A1:T1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
                $sheet->getStyle('A1')->getFont()->setBold(true);
                $sheet->setCellValue('A3','ZONES');
                $sheet->setCellValue('B3','RESIDENTIAL'); $sheet->mergeCells('B3:D3');
                $sheet->setCellValue('E3','GOVERNMENT'); $sheet->mergeCells('E3:G3');
                $sheet->setCellValue('H3','COMMERCIAL & INDUSTRIAL'); $sheet->mergeCells('H3:J3');
                $sheet->setCellValue('K3','COMMERCIAL A'); $sheet->mergeCells('K3:M3');
                $sheet->setCellValue('N3','COMMERCIAL B'); $sheet->mergeCells('N3:P3');
                $sheet->setCellValue('Q3','COMMERCIAL C'); $sheet->mergeCells('Q3:S3');
                $sheet->setCellValue('T3','TOTAL');
                $sheet->fromArray([[
                    '',
                    'NO.','CU.M','AMOUNT',
                    'NO.','CU.M','AMOUNT',
                    'NO.','CU.M','AMOUNT',
                    'NO.','CU.M','AMOUNT',
                    'NO.','CU.M','AMOUNT',
                    'NO.','CU.M','AMOUNT',
                    'TOTAL'
                ]], null, 'A4');

                $row = 5;

                $totals = [
                    'res'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'gov'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'ci'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'a'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'b'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'c'=>['count'=>0,'cum'=>0,'amt'=>0],
                    'grand'=>0
                ];

                foreach ($zones as $z) {

                    $cats = $matrix[$z] ?? [];

                    $res = $cats['RES'] ?? ['count'=>0,'cum'=>0,'amt'=>0];
                    $gov = $cats['GOV'] ?? ['count'=>0,'cum'=>0,'amt'=>0];
                    $ci  = $cats['CI']  ?? ['count'=>0,'cum'=>0,'amt'=>0];
                    $a   = $cats['A']   ?? ['count'=>0,'cum'=>0,'amt'=>0];
                    $b   = $cats['B']   ?? ['count'=>0,'cum'=>0,'amt'=>0];
                    $c   = $cats['C']   ?? ['count'=>0,'cum'=>0,'amt'=>0];

                    $total = $res['amt']+$gov['amt']+$ci['amt']+$a['amt']+$b['amt']+$c['amt'];

                    $totals['res']['count'] += $res['count'];
                    $totals['res']['cum']   += $res['cum'];
                    $totals['res']['amt']   += $res['amt'];

                    $totals['gov']['count'] += $gov['count'];
                    $totals['gov']['cum']   += $gov['cum'];
                    $totals['gov']['amt']   += $gov['amt'];

                    $totals['ci']['count'] += $ci['count'];
                    $totals['ci']['cum']   += $ci['cum'];
                    $totals['ci']['amt']   += $ci['amt'];

                    $totals['a']['count'] += $a['count'];
                    $totals['a']['cum']   += $a['cum'];
                    $totals['a']['amt']   += $a['amt'];

                    $totals['b']['count'] += $b['count'];
                    $totals['b']['cum']   += $b['cum'];
                    $totals['b']['amt']   += $b['amt'];

                    $totals['c']['count'] += $c['count'];
                    $totals['c']['cum']   += $c['cum'];
                    $totals['c']['amt']   += $c['amt'];

                    $totals['grand'] += $total;

                    $sheet->fromArray([[
                        'ZONE '.$z,

                        $res['count'],$res['cum'],$res['amt'],
                        $gov['count'],$gov['cum'],$gov['amt'],
                        $ci['count'],$ci['cum'],$ci['amt'],
                        $a['count'],$a['cum'],$a['amt'],
                        $b['count'],$b['cum'],$b['amt'],
                        $c['count'],$c['cum'],$c['amt'],

                        $total
                    ]], null, "A{$row}");

                    $row++;
                }

                $sheet->fromArray([[
                    'TOTAL',

                    $totals['res']['count'], $totals['res']['cum'], $totals['res']['amt'],
                    $totals['gov']['count'], $totals['gov']['cum'], $totals['gov']['amt'],
                    $totals['ci']['count'],  $totals['ci']['cum'],  $totals['ci']['amt'],
                    $totals['a']['count'],   $totals['a']['cum'],   $totals['a']['amt'],
                    $totals['b']['count'],   $totals['b']['cum'],   $totals['b']['amt'],
                    $totals['c']['count'],   $totals['c']['cum'],   $totals['c']['amt'],

                    $totals['grand']
                ]], null, "A{$row}");

                $sheet->getStyle("A{$row}:T{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:T{$row}")->getFont()->getColor()
                    ->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
                $sheet->getStyle('A1')->getFont()->setBold(true);


                $start = $row + 3;

                $sheet->setCellValue("A{$start}", 'PENALTY');
                $sheet->setCellValue("E{$start}", 'SENIOR DISCOUNT');
                $sheet->setCellValue("I{$start}", 'TOTAL INACTIVE ARREARS');

                $lowerStart = $start + count($zones) + 4;

                $sheet->setCellValue("A{$lowerStart}", 'TOTAL ACTIVE');
                $sheet->setCellValue("F{$lowerStart}", 'ACTIVE');
                $sheet->setCellValue("I{$lowerStart}", 'DISCONNECTED');
                $sheet->setCellValue("F".($start+10), 'ACTIVE');
                $sheet->setCellValue("I".($start+10), 'DISCONNECTED');

                $penalty = DB::table('bill')
                    ->join('readings', 'bill.reading_id', '=', 'readings.id')
                    ->select([
                        'readings.zone',
                        DB::raw('COUNT(*) as cnt'),
                        DB::raw('ROUND(SUM(bill.penalty),2) as amt'),
                    ])
                    ->where('bill.penalty', '>', 0)

                    ->whereBetween('bill.created_at', [
                        Carbon::parse($startDate)->startOfDay(),
                        Carbon::parse($endDate)->endOfDay(),
                    ])

                    ->where(function ($q) {
                        $q->whereNull('bill.date_paid')
                        ->orWhereRaw("
                            STR_TO_DATE(bill.date_paid, '%Y-%m-%d %H:%i:%s') >
                            STR_TO_DATE(bill.due_date, '%Y-%m-%d %H:%i:%s')
                        ");
                    })

                    ->groupBy('readings.zone')
                    ->get()
                    ->keyBy('zone');

                $senior = DB::table('discount as d')
                    ->join('concessioner_accounts as ca', 'd.account_no','=','ca.account_no')
                    ->join('readings as r', 'ca.account_no','=','r.account_no')
                    ->join('bill as b', 'r.id','=','b.reading_id')
                    ->selectRaw('r.zone, COUNT(DISTINCT ca.account_no) as cnt, SUM(b.discount) as amt')
                    ->whereBetween('b.created_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ])
                    ->groupBy('r.zone')
                    ->get()
                    ->keyBy('zone');

                $inactiveCutoff = $endDate
                    ? Carbon::parse($endDate)->endOfDay()
                    : now()->endOfDay();

                $inactive = DB::table('bill')
                    ->join('readings','bill.reading_id','=','readings.id')
                    ->join('concessioner_accounts','readings.account_no','=','concessioner_accounts.account_no')
                    ->selectRaw('readings.zone, COUNT(DISTINCT concessioner_accounts.account_no) as cnt, SUM(bill.total) as amt')
                    ->whereIn('concessioner_accounts.status',['ID','IV','BL'])
                    ->where('bill.isPaid', 0)
                    ->where('bill.created_at', '<=', $inactiveCutoff)
                    ->groupBy('readings.zone')
                    ->get()
                    ->keyBy('zone');

                $inactiveSummary = DB::table('concessioner_accounts as ca')
                    ->leftJoin('readings as r', 'ca.account_no', '=', 'r.account_no')
                    ->leftJoin('bill as b', 'r.id', '=', 'b.reading_id')

                    ->whereIn('ca.status', ['ID', 'IV', 'BL'])

                    ->when($endDate, function ($q) use ($endDate) {
                        $q->where('b.bill_period_to', '<=', $endDate . ' 23:59:59');
                    })

                    ->selectRaw("
                        ca.zone,
                        COUNT(DISTINCT ca.account_no) as total_accounts,
                        COALESCE(SUM(CAST(b.total AS DECIMAL(12,2))),0) as total_amount
                    ")
                    ->groupBy('ca.zone')
                    ->get()
                    ->keyBy('zone');

                $billingData = DB::table('readings as r')
                    ->join('bill as b', 'r.id', '=', 'b.reading_id')
                    ->join('concessioner_accounts as ca', 'r.account_no', '=', 'ca.account_no')

                    ->where('ca.status', 'AB')

                    ->whereBetween('b.created_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ])

                    ->selectRaw("
                        ca.account_no,

                        CASE
                            WHEN UPPER(ca.property_type) LIKE '%RESIDENTIAL%' THEN 'RESIDENTIAL'
                            WHEN UPPER(ca.property_type) LIKE '%GOVERNMENT%' THEN 'GOVERNMENT'
                            WHEN UPPER(ca.property_type) LIKE '%COMMERCIAL%' AND UPPER(ca.property_type) LIKE '%INDUSTRIAL%' THEN 'COMMERCIAL & INDUSTRIAL'
                            WHEN UPPER(ca.property_type) LIKE '%COMMERCIAL A%' THEN 'COMMERCIAL A'
                            WHEN UPPER(ca.property_type) LIKE '%COMMERCIAL B%' THEN 'COMMERCIAL B'
                            WHEN UPPER(ca.property_type) LIKE '%COMMERCIAL C%' THEN 'COMMERCIAL C'
                            ELSE 'OTHER'
                        END as category,

                        r.consumption,
                        b.total,
                        b.previous_unpaid
                    ")
                    ->get();


                $totalActiveByType = collect($billingData)
                    ->groupBy('category')
                    ->map(function ($rows) {
                        return (object)[
                            'total_accounts' => $rows->pluck('account_no')->unique()->count(),
                            'total_cum' => $rows->sum('consumption'),
                            'total_amount' => $rows->sum(function ($row) {
                                return ((float)$row->total ?? 0) - ((float)$row->previous_unpaid ?? 0);
                            }),
                        ];
                    });

                $active = DB::table('concessioner_accounts')
                    ->selectRaw('zone, COUNT(*) as cnt')
                    ->where('status','AB')
                    ->groupBy('zone')
                    ->get()
                    ->keyBy('zone');

                $disconnected = DB::table('concessioner_accounts')
                    ->selectRaw('zone, COUNT(*) as cnt')
                    ->where('status','IV')
                    ->groupBy('zone')
                    ->get()
                    ->keyBy('zone');

                $start = $row + 3;

                $r = $start + 1;

                $totalPenaltyCnt = 0;
                $totalPenaltyAmt = 0;

                foreach ($zones as $z) {
                    $cnt = $penalty[$z]->cnt ?? 0;
                    $amt = $penalty[$z]->amt ?? 0;

                    $sheet->setCellValue("A{$r}", 'ZONE '.$z);
                    $sheet->setCellValue("B{$r}", $cnt);
                    $sheet->setCellValue("C{$r}", $amt);

                    $totalPenaltyCnt += $cnt;
                    $totalPenaltyAmt += $amt;

                    $r++;
                }

                $sheet->setCellValue("A{$r}", 'TOTAL');
                $sheet->setCellValue("B{$r}", $totalPenaltyCnt);
                $sheet->setCellValue("C{$r}", $totalPenaltyAmt);


                $r = $start + 1;

                $totalSeniorCnt = 0;
                $totalSeniorAmt = 0;

                foreach ($zones as $z) {
                    $cnt = $senior[$z]->cnt ?? 0;
                    $amt = $senior[$z]->amt ?? 0;

                    $sheet->setCellValue("E{$r}", 'ZONE '.$z);
                    $sheet->setCellValue("F{$r}", $cnt);
                    $sheet->setCellValue("G{$r}", $amt);

                    $totalSeniorCnt += $cnt;
                    $totalSeniorAmt += $amt;

                    $r++;
                }

                $sheet->setCellValue("E{$r}", 'TOTAL');
                $sheet->setCellValue("F{$r}", $totalSeniorCnt);
                $sheet->setCellValue("G{$r}", $totalSeniorAmt);

                $r = $start + 1;

                $totalInactiveCnt = 0;
                $totalInactiveAmt = 0;

                foreach ($zones as $z) {
                    $cnt = $inactive[$z]->cnt ?? 0;
                    $amt = $inactive[$z]->amt ?? 0;

                    $sheet->setCellValue("I{$r}", 'ZONE '.$z);
                    $sheet->setCellValue("J{$r}", $cnt);
                    $sheet->setCellValue("K{$r}", $amt);

                    $totalInactiveCnt += $cnt;
                    $totalInactiveAmt += $amt;

                    $r++;
                }

                $sheet->setCellValue("I{$r}", 'TOTAL');
                $sheet->setCellValue("J{$r}", $totalInactiveCnt);
                $sheet->setCellValue("K{$r}", $totalInactiveAmt);


                $r2 = $start + 1;

                $totalInactiveCount = 0;
                $totalInactiveAmount = 0;

                $sheet->setCellValue("Q{$start}", 'TOTAL INACTIVE');
                $sheet->mergeCells("Q{$start}:S{$start}");

                $sheet->getStyle("Q{$start}:S{$start}")->getFont();
                $sheet->getStyle("Q{$start}:S{$start}");

                foreach ($zones as $z) {

                    $cnt = $inactive[$z]->cnt ?? 0;
                    $amt = $inactive[$z]->amt ?? 0;

                    $sheet->setCellValue("Q{$r2}", 'ZONE '.$z);
                    $sheet->setCellValue("R{$r2}", $cnt);
                    $sheet->setCellValue("S{$r2}", $amt);

                    $totalInactiveCount += $cnt;
                    $totalInactiveAmount += $amt;

                    $r2++;
                }

                $sheet->setCellValue("Q{$r2}", 'TOTAL');
                $sheet->setCellValue("R{$r2}", $totalInactiveCount);
                $sheet->setCellValue("S{$r2}", $totalInactiveAmount);

                $endRow = $r2;

                $r = $lowerStart + 1;

                $categories = [
                    'RESIDENTIAL',
                    'GOVERNMENT',
                    'COMMERCIAL & INDUSTRIAL',
                    'COMMERCIAL C',
                    'COMMERCIAL B',
                    'COMMERCIAL A',
                ];

                $totalNo = 0;
                $totalCum = 0;
                $totalAmt = 0;

                foreach ($categories as $cat) {

                    $rowData = $totalActiveByType[$cat] ?? null;

                    $count = $rowData->total_accounts ?? 0;
                    $cum   = $rowData->total_cum ?? 0;
                    $amt   = $rowData->total_amount ?? 0;

                    $sheet->setCellValue("A{$r}", $cat);
                    $sheet->setCellValue("B{$r}", $count);
                    $sheet->setCellValue("C{$r}", $cum);
                    $sheet->setCellValue("D{$r}", $amt);

                    $totalNo += $count;
                    $totalCum += $cum;
                    $totalAmt += $amt;

                    $r++;
                }

                $sheet->setCellValue("A{$r}", 'TOTAL');
                $sheet->setCellValue("B{$r}", $totalNo);
                $sheet->setCellValue("C{$r}", $totalCum);
                $sheet->setCellValue("D{$r}", $totalAmt);

                $r = $lowerStart + 1;
                $totalActive = 0;

                foreach ($zones as $z) {
                    $cnt = $active[$z]->cnt ?? 0;

                    $sheet->setCellValue("F{$r}", 'ZONE '.$z);
                    $sheet->setCellValue("G{$r}", $cnt);

                    $totalActive += $cnt;
                    $r++;
                }

                $sheet->setCellValue("F{$r}", 'TOTAL');
                $sheet->setCellValue("G{$r}", $totalActive);

                $r = $lowerStart + 1;
                $totalDisc = 0;

                foreach ($zones as $z) {
                    $cnt = $disconnected[$z]->cnt ?? 0;

                    $sheet->setCellValue("I{$r}", 'ZONE '.$z);
                    $sheet->setCellValue("J{$r}", $cnt);

                    $totalDisc += $cnt;
                    $r++;
                }

                $sheet->setCellValue("I{$r}", 'TOTAL');
                $sheet->setCellValue("J{$r}", $totalDisc);
                $sheet->getStyle("A3:T{$row}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $panelEnd = $start + count($zones) + 1; 

                $sheet->getStyle("A{$start}:C{$panelEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $sheet->getStyle("E{$start}:G{$panelEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $sheet->getStyle("I{$start}:K{$panelEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $lowerStart = $start + count($zones) + 4;
                $lowerEnd   = $lowerStart + count($zones) + 1;

                $sheet->getStyle("A{$lowerStart}:D{$lowerEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $sheet->getStyle("F{$lowerStart}:G{$lowerEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $sheet->getStyle("I{$lowerStart}:K{$lowerEnd}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $sheet->getStyle("Q{$start}:S{$endRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                foreach(range('A','T') as $col){
                    $sheet->getColumnDimension($col)->setWidth($col === 'A' ? 18 : 14);
                }

                $result[$report] = [
                    'type' => 'formatted',
                    'spreadsheet' => $spreadsheet
                ];

                break;

                default:
                    $result[$report] = [];
                    break;
            }
        }

        return $result;
    }

    protected function createFile(string $reportName, array $rows, string $format, $startDate = null, $endDate = null): string
{
    $reportsPath = storage_path('app/reports');
    if (!file_exists($reportsPath)) {
        mkdir($reportsPath, 0777, true);
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $reportName);
    $extension = ($format === 'csv') ? 'csv' : 'xlsx';
    $fileName = "{$safeName}_" . now()->format('Ymd_His') . ".{$extension}";
    $filePath = "{$reportsPath}/{$fileName}";

    if ($format === 'csv') {
        $handle = fopen($filePath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to create report file: {$filePath}");
        }

        if ($rows === []) {
            $rows = [['No Data Available']];
        }

        fputcsv($handle, ['STA RITA WATER DISTRICT']);
        fputcsv($handle, [$reportName]);
        fputcsv($handle, [$this->reportPeriodHeading($startDate, $endDate)]);
        if (isset($rows[0])) {
            if ($rows[0] !== ['No Data Available']) {
                fputcsv($handle, array_keys($rows[0]));
            }

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
        }

        fclose($handle);

        return $filePath;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($reportName, 0, 31));
    $this->addReportHeader($sheet, $reportName, $startDate, $endDate);

    $headers = !empty($rows) ? array_keys($rows[0]) : [];

    if ($format === 'plain') {
        if (!empty($headers)) {
            $sheet->fromArray([$headers], null, 'A4');
            $lastHeaderCol = chr(64 + count($headers));
            $sheet->getStyle("A4:{$lastHeaderCol}4")->getFont()->setBold(true);
            $sheet->getStyle("A4:{$lastHeaderCol}4")
                ->getAlignment()->setHorizontal('center')->setVertical('center');
        } else {
            $sheet->setCellValue('A4', 'No Data Found');
        }

        if (!empty($rows)) {
            $this->writeRowsInChunks($sheet, $rows, 5);
        }

        $sheet->getDefaultColumnDimension()->setWidth(18);
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        if (!empty($headers)) {
            $lastCol = chr(64 + count($headers));
            $sheet->getStyle("A4:{$lastCol}4")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('DDDDDD');
        }
    }

    else {
        if (!empty($rows)) {
            $sheet->fromArray([$headers], null, 'A4');
            $this->writeRowsInChunks($sheet, $rows, 5);
        } else {
            $sheet->setCellValue('A4', 'No Data Available');
        }
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

    if ($sheet->getHighestDataRow() === 0) {
        $sheet->setCellValue('A1', 'No data available');
    }

    $writer->save($filePath);
    $spreadsheet->disconnectWorksheets();

    return $filePath;
}


    protected function sanitizeFilename($name)
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($name));
    }
}
