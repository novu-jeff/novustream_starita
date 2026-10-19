<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            if (!Schema::hasColumn('bill', 'novupay_id')) {
                $table->unsignedBigInteger('novupay_id')->nullable()->after('hitpay_payment_id');
            }
            if (!Schema::hasColumn('bill', 'novupay_uid')) {
                $table->string('novupay_uid', 64)->nullable()->after('novupay_id');
            }
            if (!Schema::hasColumn('bill', 'novupay_req_id')) {
                $table->string('novupay_req_id', 128)->nullable()->index()->after('novupay_uid');
            }
            if (!Schema::hasColumn('bill', 'novupay_amount')) {
                $table->decimal('novupay_amount', 12, 2)->nullable()->after('novupay_req_id');
            }
            if (!Schema::hasColumn('bill', 'novupay_status')) {
                $table->string('novupay_status', 32)->nullable()->after('novupay_amount');
            }
            if (!Schema::hasColumn('bill', 'novupay_ref_code')) {
                $table->string('novupay_ref_code', 64)->nullable()->after('novupay_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            foreach (['novupay_id', 'novupay_uid', 'novupay_req_id', 'novupay_amount', 'novupay_status', 'novupay_ref_code'] as $column) {
                if (Schema::hasColumn('bill', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
