<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which staff member a portal/widget conversation belongs to -- so
        // each Admin, Office user, and Agent gets their own thread with a
        // customer instead of one shared inbox. Left null on Facebook-channel
        // threads and on existing rows predating this column, which stay as
        // the shared conversations they already were.
        //
        // This must match whatever `users.id` actually is in this database --
        // a signed INT in the legacy Flask-shared schema, or Laravel's own
        // BIGINT UNSIGNED default in a fresh install created after Flask's
        // retirement (see 2026_09_02_020000_create_teams_tables.php, which
        // established this same runtime-detection pattern first).
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::table('customer_messages', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $this->userKeyColumn($table, 'assigned_user_id', $userKeyType, $userKeyUnsigned)
                ->nullable()
                ->index()
                ->after('customer_id');
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
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
        Schema::table('customer_messages', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });
    }
};
