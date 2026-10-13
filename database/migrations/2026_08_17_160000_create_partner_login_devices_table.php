<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_auth')->create('partner_login_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('partner_user_id')->nullable()->index();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('device_name', 160)->nullable();
            $table->string('device_type', 20)->default('desktop'); // mobile|desktop
            $table->string('platform', 20)->nullable(); // ios|android|web
            $table->string('device_fingerprint', 64)->nullable()->index();
            $table->string('location', 160)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|logged_out
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_auth')->dropIfExists('partner_login_devices');
    }
};
