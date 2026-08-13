<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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

        // The generated-column + unique-index pair the Flask app's
        // migration 9c3a6e7b1f11 adds (a GEN-SKU brand/generic-name
        // duplicate guard) is MySQL-specific STORED generated column
        // syntax with no SQLite equivalent -- applied only when this
        // migration runs against the real shared MySQL database, not
        // the SQLite database used for local development/testing here.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE products
                ADD COLUMN gen_brand_norm VARCHAR(120)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN sku LIKE 'GEN-%' THEN NULLIF(UPPER(TRIM(COALESCE(product_name, ''))), '')
                            ELSE NULL
                        END
                    ) STORED,
                ADD COLUMN gen_generic_norm VARCHAR(120)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN sku LIKE 'GEN-%' THEN NULLIF(UPPER(TRIM(COALESCE(generic_name, ''))), '')
                            ELSE NULL
                        END
                    ) STORED
            SQL);

            DB::statement(
                'CREATE UNIQUE INDEX uq_products_gen_brand_generic_norm '
                . 'ON products (gen_brand_norm, gen_generic_norm)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
