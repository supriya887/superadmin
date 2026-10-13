<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_billing')->create('license_decrease_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('cart_id')->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->unsignedInteger('current_licenses');
            $table->unsignedInteger('requested_licenses');
            $table->string('status', 30)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_billing')->dropIfExists('license_decrease_requests');
    }
};
