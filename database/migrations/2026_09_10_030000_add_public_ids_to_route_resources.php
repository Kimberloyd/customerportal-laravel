<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
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
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('public_id')->nullable()->unique();
            });

            DB::table($table)->select('id')->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('public_id'));
        }
    }
};
