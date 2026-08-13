<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Matches the existing users table from the Flask app's schema exactly
// (same MySQL database, shared with that app during the migration) --
// no email_verified_at/remember_token/updated_at, since Flask's User
// model never had them. session_version implements Flask's "sign out
// all devices": every session carries the version it was issued under,
// and a mismatch invalidates it (see the SessionVersion middleware).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['admin', 'employee', 'customer'])->default('customer');
            $table->boolean('is_active')->default(true);
            $table->string('profile_image', 255)->nullable();
            $table->unsignedInteger('session_version')->default(0);
            $table->dateTime('created_at')->nullable();
        });

        // Laravel's own framework tables (password resets, sessions) --
        // new tables, no name collision with anything in the existing
        // schema, safe to add alongside it.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
