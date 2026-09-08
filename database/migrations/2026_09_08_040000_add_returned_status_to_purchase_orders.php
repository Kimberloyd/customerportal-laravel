<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no ALTER TABLE constraint support -- this only runs
        // against the real shared MySQL database, matching the guard on
        // the existing ck_purchase_orders_status constraint.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
            DB::statement(
                'ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status '
                ."CHECK (status IN ('submitted','partial','processing','completed','cancelled','returned'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::table('purchase_orders')
                ->where('status', 'returned')
                ->update(['status' => 'submitted']);

            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
            DB::statement(
                'ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status '
                ."CHECK (status IN ('submitted','partial','processing','completed','cancelled'))"
            );
        }
    }
};
