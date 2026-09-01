<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dateTime('customer_received_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('customer_received_at');
        });
    }
};
