<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_creation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('user_name', 255);
            $table->string('password', 255);
            $table->string('country', 255)->nullable();
            $table->string('state', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('role', 255)->nullable();
            $table->timestamps();

            // Constraints
            $table->foreign('branch_id')
                  ->references('id')
                  ->on('admin_branch')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_creation');
    }
};