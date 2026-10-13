<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_crm')->create('crm_employee_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 100)->unique();
            $table->json('permissions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_crm')->dropIfExists('crm_employee_permissions');
    }
};