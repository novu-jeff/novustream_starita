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
            if (!Schema::hasColumn('concessioner_accounts', 'application_status')) {
                $table->string('application_status')->nullable()->after('isApproved');
            }
        });

        DB::table('concessioner_accounts')
            ->where(function ($query) {
                $query->whereNotNull('application_soa_path')
                    ->orWhereNotNull('application_id_path');
            })
            ->whereNull('application_status')
            ->update([
                'application_status' => DB::raw("
                    CASE
                        WHEN isApproved = 1 THEN 'approved'
                        WHEN denied_at IS NOT NULL THEN 'denied'
                        ELSE 'pending'
                    END
                "),
            ]);
    }

    public function down(): void
    {
        Schema::table('concessioner_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('concessioner_accounts', 'application_status')) {
                $table->dropColumn('application_status');
            }
        });
    }
};
