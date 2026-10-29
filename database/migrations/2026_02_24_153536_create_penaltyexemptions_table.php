<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_exemptions', function (Blueprint $table) {
            $table->id();

            $table->string('account_no');
            $table->string('id_no')->nullable();

            $table->unsignedBigInteger('penalty_exemption_type_id')->nullable();

            $table->string('effective_date')->nullable();
            $table->string('expired_date')->nullable();

            $table->timestamps();

            $table->index('penalty_exemption_type_id');

            $table->foreign('penalty_exemption_type_id')
                ->references('id')
                ->on('penalty_exemption_type')
                ->onDelete('set null')
                ->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_exemptions');
    }
};
