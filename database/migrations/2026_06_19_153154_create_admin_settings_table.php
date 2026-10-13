<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255)->nullable();
            $table->string('logo', 250)->nullable();
            $table->string('google_transfer_code', 250)->nullable();
            $table->string('microsoft_transfer_code', 250)->nullable();
            $table->string('invoice_prefix', 20)->nullable();
            $table->string('upgrade_prefix', 20)->nullable();
            $table->string('renewal_prefix', 20)->nullable();
            $table->string('new_order_prefix', 20)->nullable();
            $table->string('order_prefix', 10)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('pincode', 255)->nullable();
            $table->string('transfert_code', 20)->nullable(); // SQL मध्ये 'transfert_code' स्पेलिंग आहे
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
