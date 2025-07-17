<?php

namespace App\Imports;

use App\Models\PropertyTypes;
use App\Models\BaseRate;
use App\Models\Rates;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RateCodesImport implements 
    ToModel, 
    WithHeadingRow, 
    WithValidation, 
    SkipsEmptyRows, 
    SkipsOnFailure,
    WithChunkReading
{
    use SkipsFailures;

    protected $skippedRows = [];
    protected $rowCounter = 3;
    protected $sheetName; 

    public function __construct($sheetName = 'Unknown Sheet')
    {
        $this->sheetName = $sheetName; 
    }

    public function model(array $row)
    {

        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {

            $property = PropertyTypes::updateOrCreate(
                ['rate_code' => $row['rate_code']], 
                [
                    'rate_code' => $row['rate_code'], 
                    'name' => $row['name'], 
                ]  
            );

            $base_rate = BaseRate::updateOrCreate(
                ['property_type_id' => $property->id],
                ['rate' => $row['rate']]
            );

            foreach ($row as $key => $value) {
                if (str_contains($key, '_')) {
                    $this->compute($property, $base_rate, $key, $value);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Import error in ConcessionaireImport', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->skippedRows[] = "Row $rowNum skipped: Exception - " . $e->getMessage();
            return null;
        }
    }


    public function rules(): array
    {
        return [
            'rate_code' => ['required'],
            'name'      => ['required'],
            'rate'      => ['required', 'numeric'],
            '0_10'      => ['required', 'numeric'],
            '11_20'     => ['required', 'numeric'],
            '21_30'     => ['required', 'numeric'],
            '31_40'     => ['required', 'numeric'],
            '41_50'     => ['required', 'numeric'],
            '51_60'     => ['required', 'numeric'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'rate_code.required' => 'Missing required field: rate_code',
            'name.required'      => 'Missing required field: name',
            'rate.required'      => 'Missing required field: rate',
            '0_10.required'      => 'Missing required field: 0-10',
            '11_20.required'     => 'Missing required field: 11-20',
            '21_30.required'     => 'Missing required field: 21-30',
            '31_40.required'     => 'Missing required field: 31-40',
            '41_50.required'     => 'Missing required field: 41-50',
            '51_60.required'     => 'Missing required field: 51-60',
        ];
    }

    public function compute($property, $base_rate, $range, $cum_charge)
    {
        [$from, $to] = explode('_', $range);

        if($from === '0') {
            for ($i = (int)$from; $i <= (int)$to; $i++) {

                $amount = $base_rate->rate;

                Rates::updateOrCreate([
                    'property_types_id' => $property->id,
                    'cu_m' => $i,
                ], [
                    'charge' => $cum_charge,
                    'amount' => $amount
                ]);
            }
        } else {
            for ($i = (int)$from; $i <= (int)$to; $i++) {
                $prevAmount = Rates::where('property_types_id', $property->id)
                    ->where('cu_m', $i - 1)
                    ->value('amount') ?? 0;

                $amount = $prevAmount + $cum_charge;

                Rates::updateOrCreate([
                    'property_types_id' => $property->id,
                    'cu_m' => $i,
                ], [
                    'charge' => $cum_charge,
                    'amount' => $amount
                ]);
            }
        }
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }

    public function getRowCounter()
    {
        return $this->rowCounter;
    }
    

}
