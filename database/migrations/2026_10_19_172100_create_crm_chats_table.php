<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pgsql_crm';

    public function up(): void
    {
        if (!Schema::connection('pgsql_crm')->hasTable('crm_chats')) {
        Schema::connection('pgsql_crm')->create('crm_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable()->unique();
            $table->string('user_a_type', 20)->nullable();
            $table->unsignedBigInteger('user_a_id')->nullable();
            $table->string('user_a_name')->nullable();
            $table->string('user_b_type', 20)->nullable();
            $table->unsignedBigInteger('user_b_id')->nullable();
            $table->string('user_b_name')->nullable();
            $table->timestamps();
            $table->unique(['user_a_type', 'user_a_id', 'user_b_type', 'user_b_id'], 'crm_chats_direct_unique');
        });
        }

        if (!Schema::connection('pgsql_crm')->hasTable('crm_chat_messages')) {
        Schema::connection('pgsql_crm')->create('crm_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id')->index();
            $table->string('sender_type', 20);
            $table->unsignedBigInteger('sender_id')->default(0);
            $table->string('sender_name');
            $table->text('message');
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql_crm')->dropIfExists('crm_chat_messages');
        Schema::connection('pgsql_crm')->dropIfExists('crm_chats');
    }
};
