<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('concessioner_account_links')
            && !Schema::hasColumn('concessioner_account_links', 'notified_at')) {
            Schema::table('concessioner_account_links', function (Blueprint $table) {
                $table->timestamp('notified_at')->nullable()->after('approved_at');
            });

            DB::table('concessioner_account_links')
                ->where('status', 'approved')
                ->whereNotNull('approved_at')
                ->update(['notified_at' => DB::raw('approved_at')]);

            DB::table('concessioner_account_links')
                ->where('status', 'denied')
                ->whereNotNull('denied_at')
                ->update(['notified_at' => DB::raw('denied_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('concessioner_account_links')
            && Schema::hasColumn('concessioner_account_links', 'notified_at')) {
            Schema::table('concessioner_account_links', function (Blueprint $table) {
                $table->dropColumn('notified_at');
            });
        }
    }
};
