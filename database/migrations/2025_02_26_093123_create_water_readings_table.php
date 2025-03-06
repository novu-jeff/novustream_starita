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
        Schema::create('water_readings', function (Blueprint $table) {
            $table->id();
            $table->string('meter_no');
            $table->string('previous_reading');
            $table->string('present_reading');
            $table->string('consumption');
            $table->timestamps();
        });

        Schema::create('water_bill', function(Blueprint $table) {
            $table->id();
            $table->foreignId('water_reading_id')
                ->constrained('water_readings')
                ->onCascade('delete');
            $table->string('reference_no');
            $table->string('bill_period_from');
            $table->string('bill_period_to');
            $table->string('previous_unpaid')
                ->nullable();
            $table->string('amount');
            $table->string('amount_paid')
                ->nullable();
            $table->boolean('isPaid')
                ->default(false);
            $table->string('date_paid')
                ->nullable();
            $table->string('due_date');
            $table->string('payor_name')
                ->nullable();
            $table->string('paid_by_reference_no')
                ->nullable();
            $table->timestamps();
        });

        Schema::create('water_bill_breakdown', function(Blueprint $table) {
            $table->id();
            $table->foreignId('water_bill_id')
                ->constrained('water_bill')
                ->onCascade('delete');
            $table->string('name');
            $table->string('description')
                ->nullable();
            $table->float('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_bill_breakdown');
        Schema::dropIfExists('water_bill');
        Schema::dropIfExists('water_readings');
    }
};
