<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_messages', function (Blueprint $table) {
            // Which staff member a portal/widget conversation belongs to -- so
            // each Admin, Office user, and Agent gets their own
            // thread with a customer instead of one shared inbox. Left null
            // on Facebook-channel threads and on existing rows predating this
            // column, which stay as the shared conversations they already were.
            // Plain `integer`, not `foreignId` -- `users.id` in this database
            // is a signed INT (ported from the legacy schema), not BIGINT.
            $table->integer('assigned_user_id')->nullable()->index()->after('customer_id');
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_messages', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });
    }
};
