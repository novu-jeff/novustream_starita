<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('bill', 'bill_period_to_ispaid_index', function (Blueprint $table) {
            $table->index(['bill_period_to', 'isPaid'], 'bill_period_to_ispaid_index');
        });
        $this->addIndexIfMissing('bill', 'bill_reference_no_index', function (Blueprint $table) {
            $table->index('reference_no', 'bill_reference_no_index');
        });
        $this->addIndexIfMissing('bill', 'bill_ispaid_date_paid_index', function (Blueprint $table) {
            $table->index(['isPaid', 'date_paid'], 'bill_ispaid_date_paid_index');
        });
        $this->addIndexIfMissing('readings', 'readings_account_no_index', function (Blueprint $table) {
            $table->index('account_no', 'readings_account_no_index');
        });
        $this->addIndexIfMissing('readings', 'readings_zone_index', function (Blueprint $table) {
            $table->index('zone', 'readings_zone_index');
        });
        $this->addIndexIfMissing('readings', 'readings_isreread_index', function (Blueprint $table) {
            $table->index('isReRead', 'readings_isreread_index');
        });
        $this->addIndexIfMissing('concessioner_accounts', 'concessioner_accounts_account_no_index', function (Blueprint $table) {
            $table->index('account_no', 'concessioner_accounts_account_no_index');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('bill', 'bill_period_to_ispaid_index');
        $this->dropIndexIfExists('bill', 'bill_reference_no_index');
        $this->dropIndexIfExists('bill', 'bill_ispaid_date_paid_index');
        $this->dropIndexIfExists('readings', 'readings_account_no_index');
        $this->dropIndexIfExists('readings', 'readings_zone_index');
        $this->dropIndexIfExists('readings', 'readings_isreread_index');
        $this->dropIndexIfExists('concessioner_accounts', 'concessioner_accounts_account_no_index');
    }

    private function addIndexIfMissing(string $table, string $name, callable $add): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, $add);
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!$this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}`");

        foreach ($rows as $row) {
            if (($row->Key_name ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
