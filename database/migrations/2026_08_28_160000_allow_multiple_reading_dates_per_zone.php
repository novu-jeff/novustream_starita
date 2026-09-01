<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_dates', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropUnique(['zone_id']);
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
            $table->index(['zone_id', 'is_active'], 'reading_dates_zone_active_idx');
            $table->index(['zone_id', 'bill_period_to'], 'reading_dates_zone_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reading_dates', function (Blueprint $table) {
            $table->dropIndex('reading_dates_zone_active_idx');
            $table->dropIndex('reading_dates_zone_period_idx');
            $table->dropForeign(['zone_id']);
            $table->unique('zone_id');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
        });
    }
};
