<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The notification bell's unread state was a single per-user watermark
 * (users.notifications_read_at, "everything up to this moment is read"),
 * chosen because most purchase_order_notifications rows are shared -- a
 * customer's own order event, or a staff-wide one with recipient_user_id
 * null -- so a single "read" column on the row itself can't hold an
 * independent state per viewer. Clicking one notification bumped the
 * watermark to now(), which reads every other notification too.
 *
 * This table adds real per-viewer, per-notification state on top of the
 * watermark rather than replacing it: the watermark stays the cheap bulk
 * "mark all read" action and the fallback for a first-ever visit; a row
 * here is the explicit "this one, for this viewer" override the watermark
 * alone can't express.
 */
return new class extends Migration
{
    public function up(): void
    {
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::create('notification_reads', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $table->id();
            $table->foreignId('purchase_order_notification_id')->constrained('purchase_order_notifications')->cascadeOnDelete();
            $this->userKeyColumn($table, 'user_id', $userKeyType, $userKeyUnsigned);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dateTime('read_at');

            $table->unique(['purchase_order_notification_id', 'user_id']);
        });
    }

    /** @return array{string, bool} */
    private function userKeyDefinition(): array
    {
        $type = Schema::getColumnType('users', 'id');
        $unsigned = false;

        if (DB::connection()->getDriverName() === 'mysql') {
            $column = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [DB::connection()->getDatabaseName(), 'users', 'id'],
            );
            $unsigned = str_contains(strtolower((string) ($column->COLUMN_TYPE ?? '')), 'unsigned');
        }

        return [$type, $unsigned];
    }

    private function userKeyColumn(Blueprint $table, string $name, string $type, bool $unsigned): ColumnDefinition
    {
        return str_contains($type, 'bigint')
            ? $table->bigInteger($name, false, $unsigned)
            : $table->integer($name, false, $unsigned);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
