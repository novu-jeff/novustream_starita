<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('service_applications', 'connection_type')) {
                $table->string('connection_type')
                    ->default('on_line');
            }

            if (!Schema::hasColumn('service_applications', 'application_fee_amount')) {
                $table->decimal('application_fee_amount', 10, 2)
                    ->default(4000);
            }

            if (!Schema::hasColumn('service_applications', 'application_fee_status')) {
                $table->string('application_fee_status')
                    ->default('unpaid');
            }
        });

        Schema::table('application_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('application_documents', 'boring_permit')) {
                $table->string('boring_permit')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
    if (!Schema::hasColumn('application_documents', 'boring_permit')) {
        $table->string('boring_permit')->nullable();
    }
});

        Schema::table('service_applications', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'connection_type',
                'application_fee_amount',
                'application_fee_status',
            ] as $column) {
                if (Schema::hasColumn('service_applications', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};