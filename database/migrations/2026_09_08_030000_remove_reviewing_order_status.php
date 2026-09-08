<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        DB::table('purchase_orders')
            ->where('status', 'reviewing')
            ->update(['status' => 'submitted']);
    }

    public function down(): void
    {
        // The former reviewing rows cannot be distinguished from submitted
        // rows after normalization, so this data migration is irreversible.
    }
};
