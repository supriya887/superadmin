<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql_crm')->hasTable('quotations')
            && !Schema::connection('pgsql_crm')->hasColumn('quotations', 'sent_at')) {
            DB::connection('pgsql_crm')->statement(
                'ALTER TABLE quotations ADD COLUMN sent_at timestamp null'
            );
        }
    }

    public function down(): void
    {
        if (Schema::connection('pgsql_crm')->hasColumn('quotations', 'sent_at')) {
            DB::connection('pgsql_crm')->statement(
                'ALTER TABLE quotations DROP COLUMN sent_at'
            );
        }
    }
};
