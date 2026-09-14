<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Incident stopgap: 2026_09_11_000000_rename_submitted_status_to_pending
 * tightened the CHECK constraint to no longer allow 'submitted', but the
 * live Flask app (app/purchase_orders/purchase_order_routes.py,
 * update_order_delivery_status()) still writes that literal value on every
 * new order and every delivery-quantity update on an order with no
 * deliveries yet -- every such write has been failing with a CHECK
 * constraint violation since that migration deployed.
 *
 * This widens the constraint back to accept 'submitted' alongside
 * 'pending', purely so Flask's writes stop erroring. It does not make
 * 'submitted' a value Laravel's own code understands -- see the paired
 * orders:normalize-legacy-submitted-status scheduled command, which
 * folds any 'submitted' row back to PurchaseOrder::STATUS_SUBMITTED
 * ('pending') within minutes, so Laravel's status filters/dashboards
 * never have to know this value exists.
 *
 * Remove this migration's effect (and the paired command) once Flask
 * either writes 'pending' directly or is retired for this table -- see
 * docs/flask-coupling.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','submitted','partial','processed','completed','cancelled','returned'))"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT ck_purchase_orders_status');
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('pending','partial','processed','completed','cancelled','returned'))"
            );
        }
    }
};
