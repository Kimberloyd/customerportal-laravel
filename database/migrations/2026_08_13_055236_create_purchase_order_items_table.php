<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity')->default(1);
            $table->integer('delivered_quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->nullable()->default(0);
            $table->decimal('line_total', 12, 2)->nullable()->default(0);
            // Snapshotted at order-creation time, deliberately independent
            // of the live products row.
            $table->string('product_name', 200)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->text('description')->nullable();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE purchase_order_items ADD CONSTRAINT ck_po_items_quantity_min '
                . 'CHECK (quantity >= 1)'
            );
            DB::statement(
                'ALTER TABLE purchase_order_items ADD CONSTRAINT ck_po_items_delivered_non_negative '
                . 'CHECK (delivered_quantity >= 0)'
            );
            DB::statement(
                'ALTER TABLE purchase_order_items ADD CONSTRAINT ck_po_items_delivered_lte_quantity '
                . 'CHECK (delivered_quantity <= quantity)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
