<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Undoes 2026_09_12_010000_allow_legacy_submitted_status_on_purchase_orders.
 * That migration was a stopgap for the legacy Flask app still writing the
 * literal 'submitted' status while it was being decoupled -- Flask is now
 * retired entirely (see docs/flask-coupling.md), so nothing can write that
 * value again. Folds any leftover 'submitted' rows to
 * PurchaseOrder::STATUS_SUBMITTED ('pending') one last time before
 * re-tightening the constraint, then the paired
 * orders:normalize-legacy-submitted-status scheduled command is removed
 * in the same commit as this migration -- it would have nothing left to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')->where('status', 'submitted')->update(['status' => 'pending']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
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
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','submitted','partial','processed','completed','cancelled','returned'))"
            );
        }
    }
};
