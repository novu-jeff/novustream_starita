<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_exemption_type', function (Blueprint $table) {
            $table->id();
            $table->string('penalty_exemption_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_exemption_type');
    }
};
