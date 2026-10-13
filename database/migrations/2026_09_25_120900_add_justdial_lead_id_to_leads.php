<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql_crm')->hasTable('leads')) {
            DB::connection('pgsql_crm')->statement(
                'ALTER TABLE leads ADD COLUMN IF NOT EXISTS justdial_lead_id varchar(255) NULL'
            );
            DB::connection('pgsql_crm')->statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS leads_justdial_lead_id_unique ON leads (justdial_lead_id)'
            );
            DB::connection('pgsql_crm')->statement(
                'ALTER TABLE leads ALTER COLUMN email DROP NOT NULL'
            );
        }
    }

    public function down(): void
    {
        DB::connection('pgsql_crm')->statement(
            'DROP INDEX IF EXISTS leads_justdial_lead_id_unique'
        );
        DB::connection('pgsql_crm')->statement(
            'ALTER TABLE leads DROP COLUMN IF EXISTS justdial_lead_id'
        );
    }
};
