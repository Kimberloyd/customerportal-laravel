<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite's ALTER TABLE has no ADD/DROP CONSTRAINT support -- this
        // only runs against the real shared MySQL database. The old
        // constraint must be dropped before the data update below, or the
        // update itself violates it (it doesn't allow 'pending' yet).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
        }

        DB::table('purchase_orders')->where('status', 'submitted')->update(['status' => 'pending']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','partial','processing','completed','cancelled','returned'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
        }

        DB::table('purchase_orders')->where('status', 'pending')->update(['status' => 'submitted']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status VARCHAR(30) NOT NULL DEFAULT 'submitted'");
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('submitted','partial','processing','completed','cancelled','returned'))"
            );
        }
    }
};
