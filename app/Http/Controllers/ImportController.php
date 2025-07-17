<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Maatwebsite\Excel\Excel as ExcelFormat;
use App\Imports\ConcessionaireImport;
use App\Imports\SCDiscountImport;
use App\Imports\RateCodesImport;
use App\Imports\StatusCodeImport;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->getMethod() !== 'POST') {
            return view('import');
        }

        if (!$request->hasFile('file')) {
            return $this->errorResponse('No file uploaded.');
        }

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();

        $sheetToProcessMap = [
            'concessionaires informations' => 'concessionaire',
            'senior citizen discount'      => 'sc_discount',
            'rates code' => 'rates_code',
            'status code' => 'status_code',
        ];

        $allowedProcesses = [
            'concessionaire' => [
                'expected_headers' => [
                    'account_no', 'name', 'address', 'rate_code', 'status',
                    'meter_brand', 'meter_serial_no', 'sc_no', 'date_connected',
                    'contact_no', 'sequence_no'
                ],
                'import_class' => \App\Imports\ConcessionaireImport::class,
                'success_message' => 'Concessionaires imported successfully.',
            ],
            'sc_discount' => [
                'expected_headers' => [
                    'account_no', 'name', 'id_no', 'effectivity_date', 'expired_date'
                ],
                'import_class' => \App\Imports\SCDiscountImport::class,
                'success_message' => 'Senior Citizen Discount imported successfully.',
            ],
            'rates_code' => [
                'expected_headers' => [
                    'rate_code', 'name', 'rate', '0_10', '11_20', '21_30', '31_40', '41_50', '51_60'
                ],
                'import_class' => \App\Imports\RateCodesImport::class,
            ],
            'status_code' => [
                'expected_headers' => [
                    'code', 'name'
                ],
                'import_class' => \App\Imports\StatusCodeImport::class,
            ]
        ];

        $allMessages = [];
        $importedSheets = [];
        $headingData = (new HeadingRowImport(2))->toArray($file);

        foreach ($sheetNames as $index => $sheetName) {

            $sheetKey = strtolower($sheetName);

            if (!isset($sheetToProcessMap[$sheetKey])) {
                $allMessages[] = [
                    'sheet'   => 'Unknown Sheet Detected',
                    'status'  => 'error',
                    'message' => 'Unrecognized file to upload',
                    'errors'  => '',
                ];
                return response()->json([
                    'status'   => 'completed',
                    'imported' => $importedSheets,
                    'messages' => $allMessages,
                ]);
            }

            $processKey = $sheetToProcessMap[$sheetKey];
            $config = $allowedProcesses[$processKey];
            $expectedHeaders = array_map('strtolower', array_map('trim', $config['expected_headers']));
            $actualHeaders = array_map('strtolower', array_map('trim', $headingData[$index][0] ?? []));

            $missingHeaders = array_diff($expectedHeaders, $actualHeaders);

            if (!empty($missingHeaders)) {
                $allMessages[] = [
                    'sheet'           => $sheetName,
                    'status'          => 'error',
                    'message'         => 'Missing headers',
                    'missing_headers' => array_values($missingHeaders),
                ];
                continue;
            }

            try {

                $importInstance = new $config['import_class']($sheetName);

                Excel::import(new class($importInstance, $sheetName) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    private $importInstance;
                    private $sheetName;

                    public function __construct($importInstance, $sheetName)
                    {
                        $this->importInstance = $importInstance;
                        $this->sheetName = $sheetName;
                    }

                    public function sheets(): array
                    {
                        return [$this->sheetName => $this->importInstance];
                    }
                }, $file);

                $importedSheets[] = $sheetName;

                $failures = $importInstance->failures();
                $failureErrors = [];

                if ($failures->isNotEmpty()) {
                    foreach ($failures as $failure) {
                        $row = $failure->row();
                        foreach ($failure->errors() as $error) {
                            $failureErrors[] = "Row [$row]: $error";
                        }
                    }
                }

                $skippedRows = method_exists($importInstance, 'getSkippedRows') ? $importInstance->getSkippedRows() : [];
                $rowCount = method_exists($importInstance, 'getRowCounter') ? $importInstance->getRowCounter() : 2;
                $totalImported = max($rowCount - 2 - count($failureErrors) - count($skippedRows), 0);

                if (!empty($failureErrors) || !empty($skippedRows)) {
                    $message = [];
                    if (!empty($failureErrors)) {
                        $message[] = count($failureErrors) . ' skipped due to logic checks';
                    }
                    if (!empty($skippedRows)) {
                        $message[] = count($skippedRows) . ' skipped due to logic checks';
                    }

                    $allMessages[] = [
                        'sheet' => $sheetName,
                        'status' => 'warning',
                        'message' => "Total of <b>(".number_format($totalImported, 0).")</b> records partially imported. <br>" . implode(', ', $message),
                        'errors' => array_merge($failureErrors, $skippedRows),
                    ];
                } else {
                    $allMessages[] = [
                        'sheet'   => $sheetName,
                        'status'  => 'success',
                        'message' => "Total of <b>(".number_format($totalImported, 0).")</b> records imported successfully.",
                    ];
                }

            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $failures = $e->failures();
                $messages = [];

                foreach ($failures as $failure) {
                    $row = $failure->row();
                    foreach ($failure->errors() as $error) {
                        $messages[] = "Row [$row]: $error";
                    }
                }

                $allMessages[] = [
                    'sheet'   => $sheetName,
                    'status'  => 'error',
                    'message' => 'Validation errors found during import.',
                    'errors'  => $messages,
                ];
            } catch (\Exception $e) {
                Log::error("Import error on sheet '$sheetName': " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);

                $allMessages[] = [
                    'sheet'   => $sheetName,
                    'status'  => 'error',
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status'   => 'completed',
            'imported' => $importedSheets,
            'messages' => $allMessages,
        ]);
    }

}
