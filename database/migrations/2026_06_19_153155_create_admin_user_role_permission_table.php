<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_role_permission', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('module_id');
            $table->string('permission', 255);

            // Constraints
            $table->foreign('role_id')
                  ->references('id')
                  ->on('admin_user_role')
                  ->onDelete('cascade');

            $table->foreign('module_id')
                  ->references('id')
                  ->on('admin_user_module')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_role_permission');
    }
};