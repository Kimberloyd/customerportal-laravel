<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_notifications', function (Blueprint $table) {
            $table->string('event_key', 80)->nullable()->after('status');
            $this->reference($table, 'recipient_user_id', 'users', nullable: true);
            $table->foreignId('follow_up_id')->nullable()->after('recipient_user_id')
                ->constrained('order_follow_ups')->nullOnDelete();
            $table->string('level', 20)->nullable()->after('follow_up_id');
            $table->string('dedupe_key', 190)->nullable()->unique()->after('level');
            $table->index(['recipient_user_id', 'channel', 'status'], 'po_notifications_recipient_channel_status');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_notifications', function (Blueprint $table) {
            $table->dropIndex('po_notifications_recipient_channel_status');
            $table->dropUnique(['dedupe_key']);
            $table->dropConstrainedForeignId('follow_up_id');
            $table->dropForeign(['recipient_user_id']);
            $table->dropColumn(['event_key', 'recipient_user_id', 'level', 'dedupe_key']);
        });
    }

    private function reference(Blueprint $table, string $name, string $referencedTable, bool $nullable = false): void
    {
        $type = Schema::getColumnType($referencedTable, 'id');
        $unsigned = false;
        if (DB::connection()->getDriverName() === 'mysql') {
            $definition = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [DB::connection()->getDatabaseName(), $referencedTable, 'id'],
            );
            $unsigned = str_contains(strtolower((string) ($definition->COLUMN_TYPE ?? '')), 'unsigned');
        }

        $column = str_contains($type, 'bigint')
            ? $table->bigInteger($name, false, $unsigned)
            : $table->integer($name, false, $unsigned);
        $column->nullable($nullable)->after('event_key');
        $table->foreign($name)->references('id')->on($referencedTable)->nullOnDelete();
    }
};
