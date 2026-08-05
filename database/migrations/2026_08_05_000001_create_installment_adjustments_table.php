<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')
                ->nullable()
                ->constrained('installments')
                ->nullOnDelete();
            $table->foreignId('bill_id')
                ->nullable()
                ->constrained('bill')
                ->nullOnDelete();
            $table->string('action');
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_adjustments');
    }
};
