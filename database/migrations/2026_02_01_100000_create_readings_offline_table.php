<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings_offline', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique()->comment('Primary key for idempotent sync; no conflict with readings/bill');
            $table->string('account_no');
            $table->decimal('previous_reading', 15, 4)->nullable();
            $table->decimal('present_reading', 15, 4)->nullable();
            $table->decimal('consumption', 15, 4)->nullable();
            $table->string('reader_name')->nullable();
            $table->string('zone')->nullable();
            $table->string('source')->default('mobile_app')->comment('mobile_app | novupay');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('merged_into_reading_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('account_no');
            $table->index(['source', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings_offline');
    }
};
