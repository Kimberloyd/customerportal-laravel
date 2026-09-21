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
        // schema) or Laravel's own bigint-unsigned default -- match whatever
        // the real column type is (same pattern as
        // 2026_09_12_000000_create_search_selections_table.php).
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::create('push_tokens', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $table->id();
            $this->userKeyColumn($table, 'user_id', $userKeyType, $userKeyUnsigned);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // One row per phone install. Unique so a phone that changes hands
            // is reassigned to whoever signs in next, not notified twice.
            $table->string('token', 512)->unique();
            $table->string('platform', 16)->default('android');
            // The login session that registered it: signing out deletes the
            // token, so one person's notifications don't follow the phone
            // after they leave it.
            $table->string('session_id', 255)->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
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
        Schema::dropIfExists('push_tokens');
    }
};
