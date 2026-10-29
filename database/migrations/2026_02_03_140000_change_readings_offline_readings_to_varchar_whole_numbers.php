<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * readings_offline: use varchar for previous_reading, present_reading, consumption
     * (same as main readings table). Store whole numbers only (no decimal point).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE readings_offline MODIFY previous_reading VARCHAR(20) NULL');
        DB::statement('ALTER TABLE readings_offline MODIFY present_reading VARCHAR(20) NULL');
        DB::statement('ALTER TABLE readings_offline MODIFY consumption VARCHAR(20) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE readings_offline MODIFY previous_reading DECIMAL(15,4) NULL');
        DB::statement('ALTER TABLE readings_offline MODIFY present_reading DECIMAL(15,4) NULL');
        DB::statement('ALTER TABLE readings_offline MODIFY consumption DECIMAL(15,4) NULL');
    }
};
