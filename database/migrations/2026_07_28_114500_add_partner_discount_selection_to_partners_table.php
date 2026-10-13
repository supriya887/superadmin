<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql_auth')->hasTable('partners')) {
            return;
        }

        Schema::connection('pgsql_auth')->table('partners', function (Blueprint $table) {
            if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'partner_discount_type')) {
                $table->string('partner_discount_type', 32)->nullable();
            }
            if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'distributor_discount')) {
                $table->decimal('distributor_discount', 5, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('pgsql_auth')->hasTable('partners')) {
            return;
        }

        Schema::connection('pgsql_auth')->table('partners', function (Blueprint $table) {
            if (Schema::connection('pgsql_auth')->hasColumn('partners', 'partner_discount_type')) {
                $table->dropColumn('partner_discount_type');
            }
            if (Schema::connection('pgsql_auth')->hasColumn('partners', 'distributor_discount')) {
                $table->dropColumn('distributor_discount');
            }
        });
    }
};
