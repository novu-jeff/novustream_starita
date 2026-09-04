<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bill', 'missing_reading_reason')) {
            Schema::table('bill', function (Blueprint $table) {
                $table->text('missing_reading_reason')->nullable()->after('high_consumption_note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bill', 'missing_reading_reason')) {
            Schema::table('bill', function (Blueprint $table) {
                $table->dropColumn('missing_reading_reason');
            });
        }
    }
};
