<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concessioner_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('concessioner_accounts', 'application_type')) {
                $table->string('application_type')->nullable()->after('application_status');
            }
        });

        DB::table('concessioner_accounts')
            ->whereNotNull('application_status')
            ->whereNull('application_type')
            ->update(['application_type' => 'existing_account']);
    }

    public function down(): void
    {
        Schema::table('concessioner_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('concessioner_accounts', 'application_type')) {
                $table->dropColumn('application_type');
            }
        });
    }
};
