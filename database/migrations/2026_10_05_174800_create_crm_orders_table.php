<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql_crm';

    public function up(): void
    {
        Schema::connection('pgsql_crm')->create('crm_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id')->unique();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->string('order_no')->unique();
            $table->string('company_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('email')->nullable();
            $table->string('employee')->nullable();
            $table->string('domain_name')->nullable();
            $table->string('plan_name')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('period')->nullable();
            $table->string('status')->default('pending');
            $table->json('plans')->nullable();
            $table->string('po_mode', 20)->nullable();
            $table->string('po_number', 80)->nullable();
            $table->date('po_date')->nullable();
            $table->decimal('po_amount', 14, 2)->nullable();
            $table->text('po_document_url')->nullable();
            $table->string('po_journey_status', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_crm')->dropIfExists('crm_orders');
    }
};
