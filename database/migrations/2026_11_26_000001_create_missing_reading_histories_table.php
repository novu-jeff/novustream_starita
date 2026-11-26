<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_reading_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reading_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->string('reference_no')->nullable()->index();
            $table->string('account_no')->index();
            $table->string('name')->nullable();
            $table->date('reading_date')->nullable();
            $table->decimal('previous_reading', 12, 2)->nullable();
            $table->decimal('present_reading', 12, 2)->nullable();
            $table->decimal('consumption', 12, 2)->nullable();
            $table->decimal('previous_unpaid', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('penalty', 12, 2)->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_reading_histories');
    }
};