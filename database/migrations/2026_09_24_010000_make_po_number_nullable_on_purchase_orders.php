<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * po_number is no longer auto-generated at creation -- it's a purely
 * staff-set reference, filled in later (or never) through the order page's
 * own inline field. MySQL's unique index already allows multiple NULLs, so
 * this only needs to drop the NOT NULL constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable(false)->change();
        });
    }
};
