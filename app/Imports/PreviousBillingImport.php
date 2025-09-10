<?php

namespace App\Imports;

use App\Models\Bill;
use App\Models\Reading;
use App\Models\SeniorDiscount;
use App\Models\BillBreakdown;
use App\Models\BillDiscount;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Services\PaymentBreakdownService;
use Carbon\Carbon;

class PreviousBillingImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    SkipsEmptyRows,
    SkipsOnFailure
{
    use SkipsFailures;

    protected $skippedRows = [];
    protected $rowCounter = 3;

    protected $sheetName;

    public function __construct($sheetName)
    {
        $this->sheetName = $sheetName;
    }

    public function rules(): array
    {
        return [
            'reference_no'      => ['required'],
            'account_no'        => ['required'],
            'billing_from'      => ['required'],
            'billing_to'        => ['required'],
            'amount'            => ['required'],

            'previous_reading'  => ['nullable', 'numeric'],
            'present_reading'   => ['nullable', 'numeric'],
            'consumption'       => ['nullable', 'numeric'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'reference_no.required'     => 'Missing required field: reference_no',
            'account_no.required'       => 'Missing required field: account_no',
            'billing_from.required'     => 'Missing required field: billing_from',
            'billing_to.required'       => 'Missing required field: billing_to',
            'amount.required'           => 'Missing required field: amount',

            'previous_reading.numeric'  => 'previous_reading must be numeric',
            'present_reading.numeric'   => 'present_reading must be numeric',
            'consumption.numeric'       => 'consumption must be numeric',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        $billing_from = $this->transformDate($row['billing_from']);
        $billing_to = $this->transformDate($row['billing_to']);
        $zone = $this->sheetName;

        $reading_id = Reading::insertGetId([
            'zone' => $zone ?? null,
            'account_no' => $row['account_no'],
            'previous_reading' => $row['previous_reading'] ?? null,
            'present_reading' => $row['present_reading'] ?? null,
            'consumption' => $row['consumption'] ?? null,
            'created_at' => $billing_from,
            'updated_at' => $billing_from,
        ]);

        $bill = Bill::create([
            'reading_id' => $reading_id,
            'reference_no' => $row['reference_no'],
            'bill_period_from' => $billing_from,
            'bill_period_to' => $billing_to,
            'previous_unpaid' => $this->cleanAmount($row['unpaid'] ?? 0),
            'penalty' => $this->cleanAmount($row['penalty'] ?? 0),
            'amount' => $this->cleanAmount($row['amount']),
            'amount_paid' => $this->cleanAmount($row['amount_paid'] ?? 0),
            'change' => $this->cleanAmount($row['change'] ?? 0),
            'isPaid' => !empty($row['amount_paid']) ? 1 : 0,
            'date_paid' => $this->transformDate($row['date_paid'] ?? null),
            'due_date' => $this->transformDate($row['due_date'] ?? null),
            'payor_name' => $row['payor_name'] ?? null,
        ]);

        $payload = [
            'account_no' => $row['account_no'],
            'previous_unpaid' => $this->cleanAmount($row['amount_paid'] ?? 0),
            'basic_charge' => $this->cleanAmount($row['amount']),
            'date' => $billing_from,
        ];

        $breakdowns = $this->create_breakdown($payload);
        foreach ($breakdowns['deductions'] as $deduction) {
            BillBreakdown::insert([
                'bill_id' => $bill->id,
                'name' => $deduction['name'],
                'description' => $deduction['description'],
                'amount' => $deduction['amount'],
                'created_at' => $billing_from,
                'updated_at' => $billing_from,
            ]);
        }

        foreach ($breakdowns['discounts'] as $discount) {
            BillDiscount::insert([
                'bill_id' => $bill->id,
                'name' => $discount['name'],
                'description' => $discount['description'],
                'amount' => $discount['amount'],
                'created_at' => $billing_from,
                'updated_at' => $billing_from,
            ]);
        }
    }

    protected function create_breakdown($payload)
    {
        $paymentBreakdownService = new PaymentBreakdownService;
        $other_deductions = $paymentBreakdownService::getData();
        $discounts = $paymentBreakdownService::getDiscounts();

        $deductions = [
            [
                'name' => 'Previous Balance',
                'amount' => $payload['previous_unpaid'],
                'description' => ''
            ],
            [
                'name' => 'Basic Charge',
                'amount' => $payload['basic_charge'],
                'description' => '',
            ],
        ];

        foreach ($other_deductions as $deduction) {
            $amount = $deduction->type === 'percentage'
                ? $payload['basic_charge'] * $deduction->amount
                : $deduction->amount;

            $deductions[] = [
                'name' => $deduction->name,
                'description' => $deduction->type === 'percentage' ? $deduction->amount . '%' : '',
                'amount' => $amount,
            ];
        }

        $sc_discount = SeniorDiscount::where('account_no', $payload['account_no'])->first();

        $appliedDiscounts = [];
        if ($sc_discount) {
            $scStartDate = Carbon::parse($sc_discount->effective_date);
            $scEndDate = Carbon::parse($sc_discount->expired_date);
            $billDate = Carbon::parse($payload['date']);

            $isEligible = $billDate->between($scStartDate, $scEndDate);
            foreach ($discounts as $discount) {
                if ($discount->eligible === 'senior' && $isEligible) {
                    $discountAmount = strtolower($discount->type) === 'percentage'
                        ? $payload['basic_charge'] * $discount->amount
                        : $discount->amount;

                    $appliedDiscounts[] = [
                        'name' => $discount->name,
                        'amount' => $discountAmount,
                        'description' => '',
                    ];
                }
            }
        }

        return [
            'deductions' => $deductions,
            'discounts' => $appliedDiscounts
        ];
    }

    protected function transformDate($value)
    {
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        if (is_string($value) && preg_match('/=DATE\((\d+),(\d+),(\d+)\)/i', $value, $matches)) {
            [$_, $year, $month, $day] = $matches;
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    protected function cleanAmount($value)
    {
        $clean = str_replace(',', '', trim($value));
        return is_numeric($clean)
            ? (fmod(floatval($clean), 1.0) === 0.0 ? (int)$clean : floatval($clean))
            : $clean;
    }

    public function chunkSize(): int
    {
        return 10000;
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function getRowCounter()
    {
        return $this->rowCounter;
    }

}
