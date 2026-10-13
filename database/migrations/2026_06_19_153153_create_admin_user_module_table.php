<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_module', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mst_id')->nullable();
            $table->string('modules', 255);
            $table->string('permissions', 255)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_module');
    }
};
