<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup: orders created before the all-digit format switch
 * still carry the old TXN-YYMMDD-XXXX transaction_number. Regenerates
 * those (and only those -- anything already in the new format is left
 * alone) using the same 3-6-3 digit generator PurchaseOrderController
 * uses for new orders, keeping the middle group as that order's own
 * creation date for continuity.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')
            ->select('id', 'submitted_at')
            ->where('transaction_number', 'like', 'TXN-%')
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    $datePart = $order->submitted_at ? Carbon::parse($order->submitted_at)->format('ymd') : now()->format('ymd');
                    do {
                        $candidate = sprintf('%03d-%s-%03d', random_int(0, 999), $datePart, random_int(0, 999));
                    } while (DB::table('purchase_orders')->where('transaction_number', $candidate)->exists());

                    DB::table('purchase_orders')->where('id', $order->id)->update(['transaction_number' => $candidate]);
                }
            });
    }

    public function down(): void
    {
        // The old TXN-YYMMDD-XXXX values aren't recoverable once
        // overwritten -- nothing meaningful to revert to.
    }
};
