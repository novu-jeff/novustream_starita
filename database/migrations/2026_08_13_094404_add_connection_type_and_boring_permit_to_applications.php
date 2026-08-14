<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_applications', function (Blueprint $table) {
            $table->string('connection_type')->default('on_line')->after('application_type_other');
            $table->decimal('application_fee_amount', 10, 2)->default(4000)->after('promissory_amount');
            $table->string('application_fee_status')->default('unpaid')->after('application_fee_amount');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->string('boring_permit')->nullable()->after('authorization_letter');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropColumn('boring_permit');
        });

        Schema::table('service_applications', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'application_fee_amount',
                'application_fee_status',
            ]);
        });
    }
};
