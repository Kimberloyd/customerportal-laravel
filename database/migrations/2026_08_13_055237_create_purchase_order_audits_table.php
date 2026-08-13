<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('action', 80);
            $table->text('details')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('created_at');
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_audits');
    }
};
