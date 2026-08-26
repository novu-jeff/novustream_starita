<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('application_documents', 'cedula')) {
                $table->string('cedula')->nullable();
            }

            if (!Schema::hasColumn('application_documents', 'proof_of_billing')) {
                $table->string('proof_of_billing')->nullable();
            }

            if (!Schema::hasColumn('application_documents', 'authorization_letter')) {
                $table->string('authorization_letter')->nullable();
            }

            if (!Schema::hasColumn('application_documents', 'boring_permit')) {
                $table->string('boring_permit')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'cedula',
                'proof_of_billing',
                'authorization_letter',
                'boring_permit',
            ] as $column) {
                if (Schema::hasColumn('application_documents', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
