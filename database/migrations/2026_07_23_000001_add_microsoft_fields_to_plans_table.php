<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::connection('pgsql_product');

        if (!$connection->hasTable('plans')) {
            return;
        }

        $connection->table('plans', function (Blueprint $table) use ($connection) {
            if (!$connection->hasColumn('plans', 'subscription_type')) {
                $table->string('subscription_type', 32)->nullable()->after('billing_cycle');
            }
            if (!$connection->hasColumn('plans', 'discount_type')) {
                $table->string('discount_type', 32)->nullable()->after('subscription_type');
            }
            if (!$connection->hasColumn('plans', 'discount_percentage')) {
                $table->decimal('discount_percentage', 8, 2)->default(0)->after('discount_type');
            }
            if (!$connection->hasColumn('plans', 'microsoft_sku_id')) {
                $table->string('microsoft_sku_id', 255)->nullable()->after('discount_percentage');
            }
            if (!$connection->hasColumn('plans', 'microsoft_product_id')) {
                $table->string('microsoft_product_id', 255)->nullable()->after('microsoft_sku_id');
            }
        });
    }

    public function down(): void
    {
        $connection = Schema::connection('pgsql_product');

        if (!$connection->hasTable('plans')) {
            return;
        }

        $connection->table('plans', function (Blueprint $table) use ($connection) {
            foreach (['microsoft_product_id', 'microsoft_sku_id', 'discount_percentage', 'discount_type', 'subscription_type'] as $column) {
                if ($connection->hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
