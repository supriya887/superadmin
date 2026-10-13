<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {

            $table->id();

            // Customer Detail
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('email');
            $table->string('mobile');
            $table->string('company_name');
            $table->string('industry')->nullable();
            $table->unsignedInteger('no_of_employees')->nullable();
            $table->string('gstin')->nullable();

            // Lead Specification
            $table->string('lead_category')->default('normal');
            $table->string('lead_status')->default('new');
            $table->string('lead_source')->nullable();
            $table->string('assigned_to')->nullable();

            // Address
            $table->text('mailing_address')->nullable();
            $table->string('country')->default('India');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('pincode')->nullable();

            // Follow Up
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_note')->nullable();

            // Services
            $table->string('domain')->nullable();
            $table->string('services')->nullable();
            $table->string('plan')->nullable();
            $table->date('renewal_date')->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();

            // Additional Contact
            $table->string('contact_name')->nullable();
            $table->string('contact_designation')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_mobile')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('lead_status');
            $table->index('lead_source');
            $table->index('assigned_to');
            $table->index('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};