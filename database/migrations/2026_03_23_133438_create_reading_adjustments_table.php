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
        Schema::create('reading_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_id')->constrained()->cascadeOnDelete();

            $table->decimal('old_present_reading', 10, 2);
            $table->decimal('new_present_reading', 10, 2);

            $table->decimal('old_consumption', 10, 2);
            $table->decimal('new_consumption', 10, 2);

            $table->text('reason');
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_adjustments');
    }
};
