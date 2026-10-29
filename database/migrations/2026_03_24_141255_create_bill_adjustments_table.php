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
        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')->constrained('bill')->cascadeOnDelete();

            // 🔥 FULL SNAPSHOT
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();

            // 🔍 quick reference fields
            $table->decimal('old_amount', 10, 2)->nullable();
            $table->decimal('new_amount', 10, 2)->nullable();

            $table->decimal('old_total', 10, 2)->nullable();
            $table->decimal('new_total', 10, 2)->nullable();

            $table->text('reason');

            $table->unsignedBigInteger('adjusted_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_adjustments');
    }
};
