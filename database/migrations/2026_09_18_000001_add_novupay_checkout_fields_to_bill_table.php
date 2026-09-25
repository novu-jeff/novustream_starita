<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            if (!Schema::hasColumn('bill', 'novupay_req_id')) {
                $table->string('novupay_req_id')->nullable()->after('hitpay_payment_id');
            }
            if (!Schema::hasColumn('bill', 'novupay_uid')) {
                $table->string('novupay_uid', 64)->nullable()->after('novupay_req_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            if (Schema::hasColumn('bill', 'novupay_uid')) {
                $table->dropColumn('novupay_uid');
            }
            if (Schema::hasColumn('bill', 'novupay_req_id')) {
                $table->dropColumn('novupay_req_id');
            }
        });
    }
};
