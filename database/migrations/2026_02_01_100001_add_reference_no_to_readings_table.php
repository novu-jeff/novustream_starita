<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            if (!Schema::hasColumn('readings', 'reference_no')) {
                $table->string('reference_no')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            if (Schema::hasColumn('readings', 'reference_no')) {
                $table->dropColumn('reference_no');
            }
        });
    }
};
