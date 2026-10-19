<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('concessioner_account_links')
            || !Schema::hasColumn('concessioner_account_links', 'notified_at')) {
            return;
        }

        DB::table('concessioner_account_links')
            ->where('status', 'denied')
            ->whereNotNull('denied_at')
            ->whereNull('notified_at')
            ->update(['notified_at' => DB::raw('denied_at')]);
    }

    public function down(): void
    {
        // Notification read state should not be cleared when rolling back this backfill.
    }
};
