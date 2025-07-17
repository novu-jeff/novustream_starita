<?php

namespace App\Imports;

use App\Models\PropertyTypes;
use App\Models\BaseRate;
use App\Models\Rates;
use App\Models\StatusCode;
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

class StatusCodeImport implements 
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

    public function rules(): array
    {
        return [
            'code' => ['required'],
            'name' => ['required'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required' => 'Missing required field: code',
            'name.required' => 'Missing required field: name',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {

            StatusCode::updateOrCreate(
                ['code' => $row['code']], 
                [
                    'code' => $row['code'],
                    'name' => $row['name']
                ]
            );
            
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

    public function compute($property, $base_rate, $range, $cum_charge)
    {
        [$from, $to] = explode('-', $range);

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
