<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('discount')) {
            Schema::create('discount', function (Blueprint $table) {
                $table->id();
                $table->string('account_no');
                $table->string('id_no')
                      ->nullable();
                $table->foreignId('discount_type_id')
                      ->nullable()
                      ->constrained('discount_type')
                      ->nullOnDelete()
                      ->restrictOnUpdate();
                $table->string('effective_date')
                      ->nullable();
                $table->string('expired_date')
                      ->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discount');
    }
};
