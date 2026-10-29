<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run after create_installments_table (same-date migrations are ordered lexically;
     * installments must exist before this FK).
     */
    public function up(): void
    {
        if (Schema::hasTable('installment_schedules')) {
            return;
        }

        Schema::create('installment_schedules', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('installment_id');
            $table->integer('month_no');
            $table->decimal('amount',10,2);
            $table->date('due_date');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign('installment_id')
                ->references('id')
                ->on('installments')
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
    }
};
