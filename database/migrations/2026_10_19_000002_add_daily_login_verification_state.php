<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'admins'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('login_otp')->nullable();
                $table->timestamp('login_otp_expires_at')->nullable();
                $table->timestamp('login_otp_verified_at')->nullable();
                $table->unsignedTinyInteger('login_otp_failed_attempts')->default(0);
                $table->timestamp('login_otp_locked_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'admins'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'login_otp',
                    'login_otp_expires_at',
                    'login_otp_verified_at',
                    'login_otp_failed_attempts',
                    'login_otp_locked_until',
                ]);
            });
        }
    }
};
