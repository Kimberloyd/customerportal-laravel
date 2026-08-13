<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audits', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40)->index();
            // Deliberately not a foreign key -- this row must survive
            // the referenced entity's deletion (often exactly the
            // action being recorded).
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('action', 80);
            $table->text('details')->nullable();
            $table->dateTime('created_at')->index();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audits');
    }
};
