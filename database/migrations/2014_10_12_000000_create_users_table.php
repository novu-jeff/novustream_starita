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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('account_no');
            $table->string('name');
            $table->string('address');
            $table->string('contact_no')
                ->nullable();
            $table->string('property_type')
                ->nullable();
            $table->integer('rate_code');
            $table->string('status');
            $table->string('meter_brand')
                ->nullable();
            $table->string('meter_serial_no');
            $table->string('sc_no');
            $table->string('date_connected');
            $table->string('sequence_no');
            $table->string('meter_type')
                ->nullable();
            $table->string('meter_wire')
                ->nullable();
            $table->string('meter_form')
                ->nullable();
            $table->string('meter_class')
                ->nullable();
            $table->string('lat_long')
                ->nullable();
            $table->boolean('isErcSealed')
                ->default(true);
            $table->string('inspection_image')
                ->nullable();
            $table->string('email')
                ->unique()
                ->nullable();
            $table->timestamp('email_verified_at')
                ->nullable();
            $table->string('password')
                ->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
