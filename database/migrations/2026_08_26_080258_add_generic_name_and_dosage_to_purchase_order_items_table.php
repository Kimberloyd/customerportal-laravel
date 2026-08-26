<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Snapshotted at order-creation time alongside product_name/sku/unit
            // (see PurchaseOrderController::store) -- deliberately independent
            // of the live inventory API data, same reasoning as those columns.
            $table->string('generic_name', 200)->nullable()->after('product_name');
            $table->string('dosage', 100)->nullable()->after('unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['generic_name', 'dosage']);
        });
    }
};
