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
        // users.id may be a plain signed int (the legacy Flask-shared
        // schema) or Laravel's own bigint-unsigned default (a fresh install
        // created after Flask's retirement) -- foreignId() always generates
        // bigint unsigned, so it can fail to constrain against the former.
        // Match whatever the real column type actually is (see
        // 2026_09_02_020000_create_teams_tables.php for this same pattern).
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::create('search_selections', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $table->id();
            $this->userKeyColumn($table, 'user_id', $userKeyType, $userKeyUnsigned);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Which search field this came from: 'customer', 'product',
            // 'message_account', or 'order_search' (free-text order list
            // queries have no entity_key, just a label).
            $table->string('context', 40);
            // A string rather than a numeric FK -- the entities searched
            // across these contexts have incompatible id shapes (integer
            // customer/product ids, prefixed message-account keys like
            // "fb-123"/"staff-45").
            $table->string('entity_key', 190)->nullable();
            $table->string('label', 190);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'context', 'created_at']);
            $table->index(['context', 'entity_key']);
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
        Schema::dropIfExists('search_selections');
    }
};
