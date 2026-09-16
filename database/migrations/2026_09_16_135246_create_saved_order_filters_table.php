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
        // Same users.id type mismatch this schema always has to account for
        // (legacy Flask-shared int vs Laravel's bigint-unsigned default) --
        // see 2026_09_12_000000_create_search_selections_table.php for the
        // original explanation of this pattern.
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::create('saved_order_filters', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $table->id();
            $this->userKeyColumn($table, 'user_id', $userKeyType, $userKeyUnsigned);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 100);
            // status, customer_id, date_filter, start_date, end_date -- the
            // same filter shape PurchaseOrderController::index() already
            // reads from the query string.
            $table->json('filters');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
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
        Schema::dropIfExists('saved_order_filters');
    }
};
