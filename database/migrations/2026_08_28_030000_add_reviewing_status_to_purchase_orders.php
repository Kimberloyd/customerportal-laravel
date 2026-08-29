<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no ALTER TABLE constraint support -- this only runs
        // against the real shared MySQL database, matching the guard on
        // the original ck_purchase_orders_status constraint.
        if (DB::connection()->getDriverName() === 'mysql') {
            // The original ck_purchase_orders_status constraint never
            // actually landed in this database (confirmed via
            // information_schema.TABLE_CONSTRAINTS -- only the PK, the
            // po_number unique index, and the customer_id FK exist), so
            // there's nothing to drop; just add it.
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('submitted','reviewing','partial','processing','completed','cancelled'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
        }
    }
};
