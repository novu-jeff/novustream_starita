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
        Schema::create('client_informations', function (Blueprint $table) {
            $table->id();
            $table->string('company_client')
                ->nullable();
            $table->string('address')
                ->nullable();
            $table->string('tel_no')
                ->nullable();
            $table->string('phone_no')
                ->nullable();
            $table->string('email')
                ->nullable();
            $table->string('tin_no')
                ->nullable();
            $table->string('bank_account_no')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_informations');
    }
};
