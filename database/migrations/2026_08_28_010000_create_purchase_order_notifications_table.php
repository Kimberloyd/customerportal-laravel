<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('recipient', 190)->nullable();
            $table->string('external_reference', 190)->nullable();
            $table->text('note')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_notifications');
    }
};
