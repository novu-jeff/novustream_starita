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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users');
            $table->string('ticket_no');
            $table->string('status');
            $table->integer('prioritization');
            $table->foreignId('category_id')
                ->constrained('tickets_category');
            $table->longText('message');
            $table->longText('feedback')
                ->nullable();
            $table->foreignId('assisted_by')
                ->nullable()
                ->constrained('users');
            $table->integer('isDeleted')
                ->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};