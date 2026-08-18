<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {

            $table->id();
            $table->foreignId('service_application_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('valid_id')->nullable();
            $table->string('cedula')->nullable()->after('valid_id');
            $table->string('proof_of_billing')->nullable()->after('cedula');
            $table->string('authorization_letter')->nullable()->after('proof_of_billing');
            $table->string('proof_of_ownership')->nullable();
            $table->string('tax_declaration')->nullable();
            $table->string('barangay_clearance')->nullable();
            $table->string('others')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
