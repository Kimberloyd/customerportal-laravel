<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->index()
                ->constrained('customers');
            $table->string('subject', 200);
            $table->text('body');
            // Self-referential: root messages have parent_id = null.
            $table->foreignId('parent_id')->nullable()->index()
                ->constrained('customer_messages')->cascadeOnDelete();
            $table->string('sender_type', 20)->default('company');
            $table->boolean('is_read')->default(true);
            // SHA-256 hex digest of the raw bearer token -- never the
            // raw token itself, same treatment as a password.
            $table->string('public_token', 100)->nullable()->unique();
            $table->dateTime('public_token_expires_at')->nullable()->index();
            $table->dateTime('public_token_revoked_at')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('error_message')->nullable();
            $table->string('channel', 30)->default('portal');
            $table->string('external_sender_id', 128)->nullable()->index();
            $table->string('external_sender_name', 200)->nullable();
            $table->dateTime('external_sender_profile_checked_at')->nullable();
            $table->string('external_page_id', 128)->nullable()->index();
            $table->string('external_message_id', 160)->nullable()->unique();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_messages');
    }
};
