<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            if (!Schema::hasColumn('bill', 'penalty_date')) {
                $table->string('penalty_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('bill', 'disconnection_date')) {
                $table->string('disconnection_date')->nullable()->after('penalty_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            if (Schema::hasColumn('bill', 'disconnection_date')) {
                $table->dropColumn('disconnection_date');
            }
            if (Schema::hasColumn('bill', 'penalty_date')) {
                $table->dropColumn('penalty_date');
            }
        });
    }
};
