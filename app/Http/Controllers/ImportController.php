<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\IOFactory;

use App\Imports\AdminAccountsImport;
use App\Imports\TechnicianAccountsImport;
use App\Imports\ConcessionaireImport;
use App\Imports\SCDiscountImport;
use App\Imports\RateCodesImport;
use App\Imports\StatusCodeImport;
use App\Imports\ClientInformationImport;
use App\Imports\SettingsImport;
use App\Imports\SequenceImport;


class ImportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->getMethod() !== 'POST') {
            return view('import');
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded.'
            ]);
        }

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();
        $filename = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $isSequenceFile = str_contains($filename, 'sequence');

        $sheetToProcessMap = [
            'client informations'         => 'client_informations',
            'client info'                 => 'client_informations',
            'admin accounts'              => 'admin_accounts',
            'administrators'              => 'admin_accounts',
            'technician accounts'         => 'technician_accounts',
            'technicians'                 => 'technician_accounts',
            'concessionaire informations' => 'concessionaire',
            'concessionaire'              => 'concessionaire',
            'sc discount'                 => 'sc_discount',
            'discount'                    => 'sc_discount',
            'discounts'                   => 'sc_discount',
            'advances'                    => 'advances',
            'outstanding balance'         => 'outstanding_balance',
            'rates code'                  => 'rates_code',
            'status code'                 => 'status_code',
            'zones'                       => 'zones',
            'settings'                    => 'settings',
            'payments'                    => 'payments',
            'Payments'                    => 'Payments',
        ];

        $processConfig = [
            'client_informations' => [
                'expected_headers' => ['name','value','description'],
                'import_class' => ClientInformationImport::class,
            ],
            'admin_accounts' => [
                'expected_headers' => ['name','role','email','password'],
                'import_class' => AdminAccountsImport::class,
            ],
            'technician_accounts' => [
                'expected_headers' => ['name','email','password'],
                'import_class' => TechnicianAccountsImport::class,
            ],
            'concessionaire' => [
                'expected_headers' => [
                    'account_no','name','address','zone','rate_code','status',
                    'meter_brand','meter_serial_no','sc_no','date_connected',
                    'contact_no','sequence_no'
                ],
                'import_class' => ConcessionaireImport::class,
            ],
            'advances' => [
                'expected_headers' => ['account_no','amount','as_of'],
                'import_class' => \App\Imports\AdvancesImport::class,
            ],
            'outstanding_balance' => [
                'expected_headers' => ['account_no','amount'],
                'import_class' => \App\Imports\OutstandingBalanceImport::class,
            ],
            'sc_discount' => [
                'expected_headers' => ['account_no','name','id_no','effectivity_date','expired_date', 'type'],
                'import_class' => SCDiscountImport::class,
            ],
            'rates_code' => [
                'expected_headers' => ['rate_code','name','rate','0_10','11_20','21_30','31_40','41_50','51_1000', '1001'],
                'import_class' => RateCodesImport::class,
            ],
            'status_code' => [
                'expected_headers' => ['code','name'],
                'import_class' => StatusCodeImport::class,
            ],
            'zones' => [
                'expected_headers' => ['zone','area'],
                'import_class' => \App\Imports\ZoneImport::class,
            ],
            'settings' => [
                'expected_headers' => ['name','value','description'],
                'import_class' => SettingsImport::class,
            ],
            'payments' => [
                'expected_headers' => ['account_no', 'amount_paid', 'payor_name', 'payment_reference_no',],
                'import_class' => \App\Imports\PaymentsImport::class,
            ],
        ];

        $normalize = function ($header) {
            $header = strtolower(trim($header));
            $header = preg_replace('/[^a-z0-9]+/i', '_', $header);
            return $header;
        };

        $headingData = (new HeadingRowImport(2))->toArray($file);

        $allMessages = [];
        $importedSheets = [];

        $isSequenceFile = str_contains(
            strtolower($file->getClientOriginalName()),
            'sequence'
        );

        foreach ($sheetNames as $index => $sheetName) {

    /*
    |-------------------------------------------------
    | SEQUENCE FILE HANDLING (by filename)
    |-------------------------------------------------
    */
    if ($isSequenceFile) {

        // Enforce Book ### sheets only
        if (!preg_match('/^book\s*\d+/i', $sheetName)) {
            $allMessages[] = [
                'sheet' => $sheetName,
                'status' => 'error',
                'message' => 'Invalid sheet name for sequence file.',
            ];
            continue;
        }

        $processKey = 'sequence';
        $config = [
            'expected_headers' => ['account_no', 'sequence_no'],
            'import_class'     => \App\Imports\SequenceImport::class,
        ];
    }

    /*
    |-------------------------------------------------
    | NORMAL IMPORT FLOW (existing behavior)
    |-------------------------------------------------
    */
    else {
        $sheetKey = strtolower(trim($sheetName));

        // Keep your Sheet1 / Worksheet filename fallback
        if (in_array($sheetKey, ['sheet1','worksheet'])) {
            foreach ($sheetToProcessMap as $alias => $process) {
                if (str_contains($filename, str_replace(' ', '', $alias))) {
                    $sheetKey = $alias;
                    break;
                }
            }
        }

        if (!isset($sheetToProcessMap[$sheetKey])) {
            $allMessages[] = [
                'sheet' => $sheetName,
                'status' => 'error',
                'message' => 'Unrecognized sheet: ' . $sheetName,
            ];
            continue;
        }

        $processKey = $sheetToProcessMap[$sheetKey];
        $config     = $processConfig[$processKey];
    }

    /*
    |-------------------------------------------------
    | HEADER VALIDATION
    |-------------------------------------------------
    */
    if (!in_array($processKey, ['client_informations', 'settings'])) {
        $expectedHeaders = array_map($normalize, $config['expected_headers']);
        $rawHeaders      = $headingData[$index][0] ?? [];
        $actualHeaders   = array_map($normalize, $rawHeaders);

        $missingHeaders = array_diff($expectedHeaders, $actualHeaders);

        if (!empty($missingHeaders)) {
            $allMessages[] = [
                'sheet' => $sheetName,
                'status' => 'error',
                'message' => 'Missing headers: ' . implode(', ', $missingHeaders),
            ];
            continue;
        }
    }

    /*
    |-------------------------------------------------
    | IMPORT EXECUTION
    |-------------------------------------------------
    */
    try {
        $importInstance = new $config['import_class']($sheetName);

        if ($processKey === 'technician_accounts' && method_exists($importInstance, 'setUserType')) {
            $importInstance->setUserType('technician');
        }

        Excel::import(new class($importInstance, $sheetName)
            implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {

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

        $successCount = method_exists($importInstance, 'getSuccessCount') ? $importInstance->getSuccessCount() : 0;
        $skippedRows  = method_exists($importInstance, 'getSkippedRows') ? $importInstance->getSkippedRows() : [];
        $total        = $successCount;

        $allMessages[] = [
            'sheet'  => $sheetName,
            'status' => count($skippedRows) ? 'warning' : 'success',
            'message'=> "Total of ({$total}) records imported.",
            'errors' => $skippedRows ?: null,
        ];

    } catch (\Exception $e) {
        Log::error("Import error on sheet '{$sheetName}'", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $allMessages[] = [
            'sheet' => $sheetName,
            'status' => 'error',
            'message' => 'An error occurred: ' . $e->getMessage(),
        ];
    }
}


        return response()->json([
            'status' => 'completed',
            'imported' => $importedSheets,
            'messages' => $allMessages,
        ]);
    }
}
