<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Products now come live from the inventoryapp catalog API instead of
 * this app's own table -- see App\Support\InventoryApiClient. This
 * only ever runs against the local/dev database: DEPLOY.md forbids
 * `php artisan migrate` against the real shared production database,
 * which this app never owns or manages.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The product_id foreign key has to go before the column itself.
        // SQLite (used by the test suite) refuses a native DROP COLUMN while
        // any foreign key still references that column, so this is required
        // on every driver -- not just MySQL -- for the migration to run.
        if (DB::connection()->getDriverName() === 'mysql') {
            // The FK constraint's real name doesn't follow Laravel's default
            // naming convention on this database (fk_po_items_product_id,
            // not purchase_order_items_product_id_foreign), so look it up
            // instead of guessing.
            $constraint = DB::selectOne(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'purchase_order_items'
                   AND COLUMN_NAME = 'product_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1"
            );

            if ($constraint) {
                Schema::table('purchase_order_items', function (Blueprint $table) use ($constraint) {
                    $table->dropForeign($constraint->CONSTRAINT_NAME);
                });
            }
        } else {
            // Laravel's SQLite grammar only supports dropping a foreign key by
            // column, never by name, and routes that through a full table
            // rebuild that omits the constraint.
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });

        Schema::dropIfExists('products');
    }

    public function down(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->nullable()->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->string('product_name', 200);
            $table->string('category', 100)->default('OTHERS');
            $table->string('generic_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable()->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('purchase_order_id')->constrained('products');
        });
    }
};
