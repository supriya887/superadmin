<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_crm')->table('leads', function (Blueprint $table) {
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain')) {
                $table->string('domain')->nullable();
            }
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain_type')) {
                $table->string('domain_type')->nullable();
            }
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain_rate')) {
                $table->decimal('domain_rate', 12, 2)->nullable();
            }
            if (!Schema::connection('pgsql_crm')->hasColumn('leads', 'domain_period')) {
                $table->string('domain_period')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_crm')->table('leads', function (Blueprint $table) {
            $drops = [];
            foreach (['domain_type', 'domain_rate', 'domain_period'] as $column) {
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
