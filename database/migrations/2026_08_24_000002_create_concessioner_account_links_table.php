<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('concessioner_account_links')) {
            return;
        }

        Schema::create('concessioner_account_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('concessioner_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requested_name');
            $table->string('soa_path');
            $table->string('id_path');
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->text('denial_reason')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
            $table->index(['status', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concessioner_account_links');
    }
};
