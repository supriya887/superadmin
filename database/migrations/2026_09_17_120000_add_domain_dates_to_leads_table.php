<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_crm')->table('leads', function (Blueprint $table) {
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'sac')) {
                $table->string('sac')->nullable();
            }
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain_start')) {
                $table->date('domain_start')->nullable();
            }
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain_end')) {
                $table->date('domain_end')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_crm')->table('leads', function (Blueprint $table) {
            $drops = [];
            foreach (['sac', 'domain_start', 'domain_end'] as $column) {
                if (Schema::connection('pgsql_crm')->hasColumn('leads', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
