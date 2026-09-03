<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('customer_id')->constrained('customers');
            // Return history remains with the order if an account is erased; the
            // requester identity is anonymized by setting this reference to null.
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('requested');
            $table->string('reason', 1000);
            $table->string('review_note', 1000)->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['purchase_order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('product_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_return_id')->constrained('product_returns')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items');
            $table->unsignedInteger('quantity');

            $table->unique(['product_return_id', 'purchase_order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_return_items');
        Schema::dropIfExists('product_returns');
    }
};
