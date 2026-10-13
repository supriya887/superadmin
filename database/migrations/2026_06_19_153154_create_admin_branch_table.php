<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_branch', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('branch_name', 250)->nullable();
            $table->integer('country')->nullable();
            $table->integer('state_id')->nullable();
            $table->string('state_name', 250)->nullable();
            $table->string('city', 250)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_branch');
    }
};