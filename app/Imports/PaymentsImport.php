<?php

namespace App\Imports;

use App\Models\Bill;
use App\Models\Reading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PaymentsImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;
    protected array $skippedRows = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            try {
                $accountNo  = trim($row['account_no'] ?? '');
                $amountPaid = $row['amount_paid'] ?? null;
                $payorName  = $row['payor_name'] ?? null;
                $referenceNo = $row['payment_reference_no'] ?? null;

                if ($accountNo === '' || $amountPaid === null) {
                    $this->skip($index, 'Missing account_no or amount_paid');
                    continue;
                }

                $reading = Reading::where('account_no', $accountNo)
                    ->latest('id')
                    ->first();

                if (!$reading) {
                    $this->skip($index, 'No reading found for account');
                    continue;
                }

                $bill = Bill::where('reading_id', $reading->id)
                    ->orderByDesc('id')
                    ->first();

                if (!$bill) {
                    $this->skip($index, 'No bill found for reading');
                    continue;
                }

                if ((int)$bill->isPaid === 1) {
                    $this->skip($index, 'Bill already paid');
                    continue;
                }

                if ($referenceNo &&
                    Bill::where('paid_by_reference_no', $referenceNo)->exists()
                ) {
                    $this->skip($index, 'Duplicate payment reference number');
                    continue;
                }

                $bill->update([
                    'amount_paid'          => $amountPaid,
                    'isPaid'               => 1,
                    'date_paid'            => Carbon::now()->toDateString(),
                    'payor_name'           => $payorName,
                    'paid_by_reference_no' => $referenceNo,
                    'payment_method'       => 'cash',
                ]);

                $this->successCount++;

            } catch (\Throwable $e) {
                $this->skip($index, $e->getMessage());
            }
        }
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }

    public function headingRow(): int
    {
        return 2;
    }

    protected function skip(int $index, string $reason): void
    {
        $this->skippedRows[] = [
            'row'    => $index + 3,
            'reason' => $reason,
        ];
    }
}
