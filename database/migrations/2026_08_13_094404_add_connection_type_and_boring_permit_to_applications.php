<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_applications', function (Blueprint $table) {
            $table->string('connection_type')->default('on_line');
            $table->decimal('application_fee_amount', 10, 2)->default(4000);
            $table->string('application_fee_status')->default('unpaid');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->string('boring_permit')->nullable();
            $table->string('cedula')->nullable();
            $table->string('proof_of_billing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropColumn([
                'boring_permit', 
                'cedula',
            ]);
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
