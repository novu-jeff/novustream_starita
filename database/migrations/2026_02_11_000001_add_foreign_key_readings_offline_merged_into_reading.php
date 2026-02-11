<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean orphaned references (merged_into_reading_id pointing to non-existent readings)
        DB::statement('
            UPDATE readings_offline ro
            LEFT JOIN readings r ON ro.merged_into_reading_id = r.id
            SET ro.merged_into_reading_id = NULL
            WHERE ro.merged_into_reading_id IS NOT NULL
            AND r.id IS NULL
        ');

        Schema::table('readings_offline', function (Blueprint $table) {
            $table->foreign('merged_into_reading_id')
                ->references('id')
                ->on('readings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('readings_offline', function (Blueprint $table) {
            $table->dropForeign(['merged_into_reading_id']);
        });
    }
};
