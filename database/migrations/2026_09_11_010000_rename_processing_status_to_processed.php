<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
        }

        DB::table('purchase_orders')->where('status', 'processing')->update(['status' => 'processed']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','partial','processed','completed','cancelled','returned'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
        }

        DB::table('purchase_orders')->where('status', 'processed')->update(['status' => 'processing']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','partial','processing','completed','cancelled','returned'))"
            );
        }
    }
};
