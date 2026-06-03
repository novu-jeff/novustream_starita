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
        if (Schema::hasTable('installments')) {
            return;
        }

        Schema::create('installments', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('bill_amount',10,2);
            $table->integer('months');
            $table->decimal('monthly_amount',10,2);
            $table->enum('status',[
                'active',
                'completed',
                'cancelled'
            ])->default('active');
            $table->timestamps();
            $table->foreign('bill_id')->references('id')->on('bill')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('concessioner_accounts')->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
