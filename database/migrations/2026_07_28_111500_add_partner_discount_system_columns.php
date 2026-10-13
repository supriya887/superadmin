<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('pgsql_auth')->hasTable('partners')) {
            Schema::connection('pgsql_auth')->table('partners', function (Blueprint $table) {
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'discount_percent')) {
                    $table->decimal('discount_percent', 5, 2)->default(0);
                }
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'market_value_discount_type')) {
                    $table->string('market_value_discount_type', 20)->default('percent');
                }
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'market_value_discount_amount')) {
                    $table->decimal('market_value_discount_amount', 12, 2)->default(0);
                }
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'distributor_discount')) {
                    $table->decimal('distributor_discount', 5, 2)->default(0);
                }
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'distributor_discount_type')) {
                    $table->string('distributor_discount_type', 20)->default('percent');
                }
                if (!Schema::connection('pgsql_auth')->hasColumn('partners', 'distributor_discount_amount')) {
                    $table->decimal('distributor_discount_amount', 12, 2)->default(0);
                }
            });
        }

        if (Schema::connection('pgsql_billing')->hasTable('carts')
            && !Schema::connection('pgsql_billing')->hasColumn('carts', 'partner_discount_type')) {
            Schema::connection('pgsql_billing')->table('carts', function (Blueprint $table) {
                $table->string('partner_discount_type', 30)->nullable()->default('none');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('pgsql_billing')->hasTable('carts')
            && Schema::connection('pgsql_billing')->hasColumn('carts', 'partner_discount_type')) {
            Schema::connection('pgsql_billing')->table('carts', function (Blueprint $table) {
                $table->dropColumn('partner_discount_type');
            });
        }

        if (!Schema::connection('pgsql_auth')->hasTable('partners')) {
            return;
        }

        Schema::connection('pgsql_auth')->table('partners', function (Blueprint $table) {
            foreach ([
                'market_value_discount_type',
                'market_value_discount_amount',
                'distributor_discount_type',
                'distributor_discount_amount',
            ] as $col) {
                if (Schema::connection('pgsql_auth')->hasColumn('partners', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
