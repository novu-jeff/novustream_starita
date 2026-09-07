<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_deletion_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reading_id');
            $table->string('account_no');
            $table->string('name')->nullable();
            $table->timestamp('reading_date')->nullable();
            $table->decimal('previous_reading', 10, 2)->nullable();
            $table->decimal('present_reading', 10, 2)->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_deletion_histories');
    }
};
