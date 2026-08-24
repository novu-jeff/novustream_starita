<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('application_documents', 'proof_of_billing')) {
                $table->string('proof_of_billing')->nullable();
            }

            if (!Schema::hasColumn('application_documents', 'authorization_letter')) {
                $table->string('authorization_letter')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('application_documents', 'proof_of_billing')) {
                $columns[] = 'proof_of_billing';
            }

            if (Schema::hasColumn('application_documents', 'authorization_letter')) {
                $columns[] = 'authorization_letter';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};