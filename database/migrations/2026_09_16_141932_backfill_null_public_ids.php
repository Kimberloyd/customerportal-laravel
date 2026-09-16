<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Same tables 2026_09_10_030000_add_public_ids_to_route_resources.php
     * backfilled -- that migration only ran once, against rows that existed
     * at that moment. Every model in this list has a HasPublicId trait
     * (App\Models\Concerns\HasPublicId) that generates one on create()
     * going forward for anything Eloquent inserts, but this database is
     * shared with a legacy Flask app (see docker-compose/architecture
     * notes) that writes to these same tables directly over raw SQL,
     * bypassing that model hook entirely -- confirmed live: the seeded
     * admin@theomeds.com row had a NULL public_id, which breaks every
     * route keyed on it (reset-password, toggle-active, destroy, restore,
     * data-export all 404/throw a Ziggy "parameter is required" error).
     * Safe to rerun anytime; it only ever touches rows still NULL.
     */
    private const TABLES = [
        'users',
        'customers',
        'purchase_orders',
        'product_returns',
        'customer_messages',
        'teams',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('public_id')->orderBy('id')->select('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Backfilling a NULL column has no meaningful inverse -- reversing
        // it would just re-break every route keyed on these ids.
    }
};
