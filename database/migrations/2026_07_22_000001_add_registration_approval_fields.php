<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type')->nullable()->after('email');
            }
        });

        Schema::table('concessioner_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('concessioner_accounts', 'isApproved')) {
                $table->boolean('isApproved')->default(false)->after('inspection_image');
            }

            if (!Schema::hasColumn('concessioner_accounts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('isApproved');
            }

            if (!Schema::hasColumn('concessioner_accounts', 'denied_at')) {
                $table->timestamp('denied_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('concessioner_accounts', 'application_soa_path')) {
                $table->string('application_soa_path')->nullable()->after('denied_at');
            }

            if (!Schema::hasColumn('concessioner_accounts', 'application_id_path')) {
                $table->string('application_id_path')->nullable()->after('application_soa_path');
            }

            if (!Schema::hasColumn('concessioner_accounts', 'approval_denial_reason')) {
                $table->text('approval_denial_reason')->nullable()->after('application_id_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('concessioner_accounts', function (Blueprint $table) {
            foreach ([
                'approval_denial_reason',
                'application_id_path',
                'application_soa_path',
                'denied_at',
            ] as $column) {
                if (Schema::hasColumn('concessioner_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropColumn('user_type');
            }
        });
    }
};
