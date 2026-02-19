<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add status column to prevent duplicate readings from re-entering merge queue.
     * Values: pending (default), merged, skipped_duplicate, rejected
     */
    public function up(): void
    {
        Schema::table('readings_offline', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->nullable()->after('source')
                ->comment('pending|merged|skipped_duplicate|rejected');
        });
        // Mark already-synced rows for clarity
        DB::table('readings_offline')->whereNotNull('synced_at')->update(['status' => 'merged']);
    }

    public function down(): void
    {
        Schema::table('readings_offline', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
