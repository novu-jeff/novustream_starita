<?php

namespace App\Http\Controllers;

use App\Models\ReadingOffline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Settings > Offline Readings: run readings:merge and list pending/recent merged.
 */
class OfflineReadingsController extends Controller
{
    /**
     * Settings > Offline Readings page.
     */
    public function index()
    {
        return view('settings.offline-readings');
    }

    /**
     * Pending readings_offline ready to merge (with optional filters).
     */
    public function readingsReadyToMerge(Request $request): JsonResponse
    {
        if (!Schema::hasTable('readings_offline')) {
            return response()->json([
                'count' => 0,
                'readings' => [],
            ]);
        }

        $limit = (int) $request->input('limit', 50);
        $limit = in_array($limit, [10, 20, 50, 100, 500], true) ? $limit : 50;

        $query = ReadingOffline::where(function ($q) {
            $q->whereNull('status')->orWhere('status', 'pending');
        })
            ->whereNull('synced_at')
            ->whereNull('merged_into_reading_id')
            ->orderBy('id');

        $this->applyOfflineFilters($query, $request);
        $readings = $query->limit($limit)->get();
        $list = $readings->map(fn ($r) => $this->offlineRowToArray($r))->values()->all();

        return response()->json([
            'count' => count($list),
            'readings' => $list,
        ]);
    }

    /**
     * Run readings:merge with optional limit, return count and output.
     */
    public function runMerge(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 500);
        $limit = in_array($limit, [50, 100, 200, 500], true) ? $limit : 500;
        $dryRun = $request->boolean('dry_run');

        try {
            $options = ['--limit' => $limit];
            if ($dryRun) {
                $options['--dry-run'] = true;
            }
            Artisan::call('readings:merge', $options);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Merge failed: ' . $e->getMessage(),
                'count' => 0,
                'output' => '',
            ], 500);
        }

        preg_match('/Merged:\s*(\d+)/', $output, $m);
        $count = isset($m[1]) ? (int) $m[1] : 0;
        if ($dryRun && preg_match('/would be merged/', $output)) {
            preg_match('/(\d+)\s+pending/', $output, $m2);
            $count = isset($m2[1]) ? (int) $m2[1] : 0;
        }

        return response()->json([
            'status' => 'success',
            'message' => $dryRun ? 'Dry run completed.' : 'Merge completed.',
            'count' => $count,
            'output' => $output,
        ]);
    }

    /**
     * Recently merged readings_offline (with optional filters).
     */
    public function recentMerged(Request $request): JsonResponse
    {
        if (!Schema::hasTable('readings_offline')) {
            return response()->json([
                'count' => 0,
                'readings' => [],
            ]);
        }

        $limit = (int) $request->input('limit', 20);
        $limit = in_array($limit, [10, 20, 50, 100, 500], true) ? $limit : 20;

        $query = ReadingOffline::whereNotNull('merged_into_reading_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $this->applyOfflineFilters($query, $request);
        $readings = $query->limit($limit)->get();
        $list = $readings->map(fn ($r) => $this->offlineRowToArray($r))->values()->all();

        return response()->json([
            'count' => count($list),
            'readings' => $list,
        ]);
    }

    private function applyOfflineFilters($query, Request $request): void
    {
        if ($request->filled('account_no')) {
            $query->where('account_no', 'like', '%' . $request->input('account_no') . '%');
        }
        if ($request->filled('reference_no')) {
            $query->where('reference_no', 'like', '%' . $request->input('reference_no') . '%');
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
    }

    private function offlineRowToArray(ReadingOffline $r): array
    {
        return [
            'reference_no' => $r->reference_no,
            'account_no' => $r->account_no,
            'previous_reading' => $r->previous_reading,
            'present_reading' => $r->present_reading,
            'consumption' => $r->consumption,
            'source' => $r->source,
            'reader_name' => $r->reader_name,
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
            'updated_at' => $r->updated_at?->format('Y-m-d H:i'),
            'merged_into_reading_id' => $r->merged_into_reading_id,
        ];
    }
}
