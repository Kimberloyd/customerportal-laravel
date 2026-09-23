<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Splits the order's identifier in two: po_number stays the staff-owned
 * reference (blank/auto-generated until staff sets a real one), while
 * transaction_number is the customer-facing identifier, generated at
 * creation time and never editable by anyone. Nullable+unique added first,
 * backfilled per row (can't compute a batch-unique default in a single SQL
 * statement), then locked to NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('transaction_number', 100)->nullable()->unique()->after('po_number');
        });

        DB::table('purchase_orders')->select('id', 'submitted_at')->orderBy('id')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                $datePart = $order->submitted_at ? Carbon::parse($order->submitted_at)->format('ymd') : now()->format('ymd');
                do {
                    $candidate = 'TXN-'.$datePart.'-'.Str::upper(Str::random(4));
                } while (DB::table('purchase_orders')->where('transaction_number', $candidate)->exists());

                DB::table('purchase_orders')->where('id', $order->id)->update(['transaction_number' => $candidate]);
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('transaction_number', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('transaction_number');
        });
    }
};
