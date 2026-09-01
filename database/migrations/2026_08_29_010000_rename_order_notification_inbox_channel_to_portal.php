<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_order_notifications')
            ->where('channel', 'inbox')
            ->update(['channel' => 'portal']);
    }

    public function down(): void
    {
        DB::table('purchase_order_notifications')
            ->where('channel', 'portal')
            ->update(['channel' => 'inbox']);
    }
};
