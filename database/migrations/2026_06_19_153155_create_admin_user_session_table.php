<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_session', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('logged_in')->nullable();
            $table->timestamp('logout')->nullable();
            $table->string('ip_address', 255)->nullable();

            // Constraints
            $table->foreign('user_id')
                  ->references('id')
                  ->on('admin_user_creation')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_session');
    }
};
