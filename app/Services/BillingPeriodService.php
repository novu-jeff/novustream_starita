<?php

namespace App\Services;

use App\Models\ReadingDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingPeriodService
{
    /**
     * Active billing period anchor from majority of active reading_dates bill_period_to.
     * Falls back to server clock when no active rows exist.
     */
    public function activePeriod(): Carbon
    {
        $rows = ReadingDate::query()
            ->where('is_active', 1)
            ->whereNotNull('bill_period_to')
            ->orderBy('zone_id')
            ->get();

        if ($rows->isEmpty()) {
            return now()->copy()->startOfDay();
        }

        $counts = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->bill_period_to)->format('Y-m-d');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        arsort($counts);
        $topDate = array_key_first($counts);

        return Carbon::parse($topDate)->startOfDay();
    }

    public function year(): int
    {
        return (int) $this->activePeriod()->year;
    }

    public function month(): int
    {
        return (int) $this->activePeriod()->month;
    }

    public function yearMonth(): string
    {
        return $this->activePeriod()->format('Y-m');
    }

    /** First day of the active billing month (for prior-period cutoff). */
    public function periodStart(): Carbon
    {
        return $this->activePeriod()->copy()->startOfMonth()->startOfDay();
    }

    /**
     * Whether a bill's bill_period_to falls in the active billing month.
     */
    public function isInActivePeriod($billPeriodTo): bool
    {
        if (empty($billPeriodTo)) {
            return false;
        }

        $period = Carbon::parse($billPeriodTo);

        return $period->year === $this->year() && $period->month === $this->month();
    }

    /**
     * Staging: shift all active reading_dates so bill_period_to lands in target yyyy-MM.
     * Preserves day-of-month offsets between from / to / due (same as demo app).
     */
    public function simulateMonth(string $targetYearMonth): int
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $targetYearMonth)) {
            throw new \InvalidArgumentException('Target month must be yyyy-MM.');
        }

        [$targetYear, $targetMonth] = array_map('intval', explode('-', $targetYearMonth));
        $targetEnd = Carbon::create($targetYear, $targetMonth, 1)->endOfMonth();

        $activeRows = ReadingDate::query()->where('is_active', 1)->get();
        if ($activeRows->isEmpty()) {
            throw new \RuntimeException('No active reading dates to simulate.');
        }

        $this->saveSnapshot($activeRows);

        $updated = 0;
        foreach ($activeRows as $row) {
            $currentTo = Carbon::parse($row->bill_period_to);
            $targetDay = min($currentTo->day, $targetEnd->day);
            $newTo = Carbon::create($targetYear, $targetMonth, $targetDay);

            $from = Carbon::parse($row->bill_period_from);
            $due = Carbon::parse($row->due_date);
            $fromOffsetDays = $from->diffInDays($currentTo, false);
            $dueOffsetDays = $due->diffInDays($currentTo, false);

            $newFrom = $newTo->copy()->addDays($fromOffsetDays);
            $newDue = $newTo->copy()->addDays($dueOffsetDays);

            $row->update([
                'bill_period_from' => $newFrom->format('Y-m-d'),
                'bill_period_to' => $newTo->format('Y-m-d'),
                'due_date' => $newDue->format('Y-m-d'),
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Restore reading_dates from last simulateMonth snapshot.
     */
    public function restoreFromSnapshot(): int
    {
        $row = DB::table('billing_period_snapshots')->orderByDesc('id')->first();
        $snapshot = $row ? json_decode($row->payload, true) : null;
        if (empty($snapshot) || !is_array($snapshot)) {
            throw new \RuntimeException('No billing period snapshot to restore.');
        }

        $restored = 0;
        foreach ($snapshot as $item) {
            $readingDate = ReadingDate::find($item['id'] ?? null);
            if (!$readingDate) {
                continue;
            }
            $readingDate->update([
                'bill_period_from' => $item['bill_period_from'],
                'bill_period_to' => $item['bill_period_to'],
                'due_date' => $item['due_date'],
                'is_active' => $item['is_active'] ?? true,
            ]);
            $restored++;
        }

        DB::table('billing_period_snapshots')->truncate();

        return $restored;
    }

    public function hasSnapshot(): bool
    {
        return DB::table('billing_period_snapshots')->exists();
    }

    private function saveSnapshot($activeRows): void
    {
        $payload = $activeRows->map(fn ($row) => [
            'id' => $row->id,
            'zone_id' => $row->zone_id,
            'bill_period_from' => $row->bill_period_from,
            'bill_period_to' => $row->bill_period_to,
            'due_date' => $row->due_date,
            'is_active' => (bool) $row->is_active,
        ])->values()->all();

        DB::table('billing_period_snapshots')->truncate();
        DB::table('billing_period_snapshots')->insert([
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
