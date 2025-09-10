<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Imports\AdminAccountsImport;
use Maatwebsite\Excel\Excel as ExcelFormat;
use App\Imports\ConcessionaireImport;
use App\Imports\SCDiscountImport;
use App\Imports\RateCodesImport;
use App\Imports\StatusCodeImport;
use App\Imports\ClientInformationImport;
use App\Imports\SettingsImport;

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

        // Map of supported sheet names -> process keys
        $sheetToProcessMap = [
            'client informations'       => 'client_informations',
            'admin accounts'            => 'admin_accounts',
            'technician accounts'       => 'technician_accounts',
            'concessionaire informations' => 'concessionaire',
            'concessionaire'            => 'concessionaire', // alias
            'advances'                  => 'advances',
            'outstanding balance'       => 'outstanding_balance',
            'senior citizen discount'   => 'sc_discount',
            'rates code'                => 'rates_code',
            'status code'               => 'status_code',
            'zones'                     => 'zones',
            'settings'                  => 'settings',
        ];

        // Expected headers + import class mapping
        $allowedProcesses = [
            'client_informations' => [
                'expected_headers' => ['name', 'value', 'description'],
                'import_class' => \App\Imports\ClientInformationImport::class,
                'success_message' => 'Client Informations imported successfully.',
            ],
            'admin_accounts' => [
                'expected_headers' => ['name', 'email', 'user_type', 'password'],
                'import_class' => \App\Imports\AdminAccountsImport::class,
                'success_message' => 'Admin Accounts imported successfully.',
            ],
            'technician_accounts' => [
                'expected_headers' => ['name', 'email', 'password'],
                'import_class' => \App\Imports\TechnicianAccountsImport::class,
                'success_message' => 'Technician Accounts imported successfully.',
            ],
            'concessionaire' => [
                'expected_headers' => [
                    'account_no', 'name', 'address', 'rate_code', 'status',
                    'meter_brand', 'meter_serial_no', 'sc_no', 'date_connected',
                    'contact_no', 'sequence_no'
                ],
                'import_class' => \App\Imports\ConcessionaireImport::class,
                'success_message' => 'Concessionaires imported successfully.',
            ],
            'advances' => [
                'expected_headers' => ['account_no', 'amount', 'date_applied'],
                'import_class' => \App\Imports\AdvancesImport::class,
                'success_message' => 'Advances imported successfully.',
            ],
            'outstanding_balance' => [
                'expected_headers' => ['account_no', 'amount'],
                'import_class' => \App\Imports\OutstandingBalanceImport::class,
                'success_message' => 'Outstanding Balances imported successfully.',
            ],
            'sc_discount' => [
                'expected_headers' => ['account_no', 'name', 'id_no', 'effectivity_date', 'expired_date'],
                'import_class' => \App\Imports\SCDiscountImport::class,
                'success_message' => 'Senior Citizen Discounts imported successfully.',
            ],
            'rates_code' => [
                'expected_headers' => ['rate_code', 'name', 'rate', '0_10', '11_20', '21_30', '31_40', '41_50', '51_60'],
                'import_class' => \App\Imports\RateCodesImport::class,
                'success_message' => 'Rate Codes imported successfully.',
            ],
            'status_code' => [
                'expected_headers' => ['code', 'name'],
                'import_class' => \App\Imports\StatusCodeImport::class,
                'success_message' => 'Status Codes imported successfully.',
            ],
            'zones' => [
                'expected_headers' => ['zone', 'area'],
                'import_class' => \App\Imports\ZoneImport::class,
                'success_message' => 'Zones imported successfully.',
            ],
            'settings' => [
                'expected_headers' => ['name', 'value', 'description'],
                'import_class' => \App\Imports\SettingsImport::class,
                'success_message' => 'Settings imported successfully.',
            ],
        ];

        $allMessages = [];
        $importedSheets = [];
        $headingData = (new HeadingRowImport(1))->toArray($file);

        foreach ($sheetNames as $index => $sheetName) {
            $sheetKey = strtolower(trim($sheetName));

            $filename = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

            if (in_array($sheetKey, ['sheet1', 'worksheet'])) {
                if (str_contains($filename, 'admin')) {
                    $sheetKey = 'admin accounts';
                } elseif (str_contains($filename, 'zones')) {
                    $sheetKey = 'zones';
                } elseif (str_contains($filename, 'concessionaire')) {
                    $sheetKey = 'concessionaire informations';
                } elseif (str_contains($filename, 'rates code')) {
                    $sheetKey = 'rates code';
                } elseif (str_contains($filename, 'client informations')) {
                    $sheetKey = 'client informations';
                } elseif (str_contains($filename, 'technician accounts')) {
                    $sheetKey = 'technician accounts';
                } elseif (str_contains($filename, 'settings')) {
                    $sheetKey = 'settings';
                }
            }


            if (!isset($sheetToProcessMap[$sheetKey])) {
                $allMessages[] = [
                    'sheet'   => $sheetName,
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
                $rowCount = method_exists($importInstance, 'getRowCounter') ? $importInstance->getRowCounter() : 1;

                if (in_array($sheetKey, ['client informations', 'settings'])) {
                    $totalImported = $rowCount;
                } else {
                    $totalImported = max($rowCount - 1 - count($failureErrors) - count($skippedRows), 0);
                }

                if ($processKey === 'admin_accounts') {
                    $totalImported = $importInstance->getImportedCount();
                } else {
                    $skippedRows = method_exists($importInstance, 'getSkippedRows') ? $importInstance->getSkippedRows() : [];
                    $rowCount = method_exists($importInstance, 'getRowCounter') ? $importInstance->getRowCounter() : 1;
                    $totalImported = max($rowCount - 1 - count($failureErrors) - count($skippedRows), 0);
                }

                if (!empty($failureErrors) || !empty($skippedRows)) {
                    $message = [];
                    if (!empty($failureErrors)) {
                        $message[] = count($failureErrors) . ' failed validation';
                    }
                    if (!empty($skippedRows)) {
                        $message[] = count($skippedRows) . ' skipped due to logic checks';
                    }

                    $allMessages[] = [
                        'sheet' => $sheetName,
                        'status' => 'warning',
                        'message' => "Total of <b>(" . number_format($totalImported, 0) . ")</b> records partially imported. <br>" . implode(', ', $message),
                        'errors' => array_merge($failureErrors, $skippedRows),
                    ];
                } else {
                    $allMessages[] = [
                        'sheet'   => $sheetName,
                        'status'  => 'success',
                        'message' => "Total of <b>(" . number_format($totalImported, 0) . ")</b> records imported successfully.",
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
