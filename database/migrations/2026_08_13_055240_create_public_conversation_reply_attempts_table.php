<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_conversation_reply_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_message_id')->index()
                ->constrained('customer_messages')->cascadeOnDelete();
            $table->string('client_key', 64)->index();
            $table->dateTime('attempted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_conversation_reply_attempts');
    }
};
