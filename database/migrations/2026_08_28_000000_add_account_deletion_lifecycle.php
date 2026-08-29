<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'deactivated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('deactivated_at')->nullable()->index()->after('session_version');
            });
        }

        if (! Schema::hasColumn('users', 'purge_after')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('purge_after')->nullable()->index()->after('deactivated_at');
            });
        }

        if (! Schema::hasColumn('users', 'deletion_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('deletion_reason', 80)->nullable()->after('purge_after');
            });
        }

        if (! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }

        // MySQL cannot roll back DDL. If a previous attempt stopped while
        // adding the foreign keys, this migration-owned table is incomplete
        // and unusable, so rebuild it before retrying.
        Schema::dropIfExists('data_subject_requests');

        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        Schema::create('data_subject_requests', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
            $table->uuid('id')->primary();
            $this->userKeyColumn($table, 'subject_user_id', $userKeyType, $userKeyUnsigned);
            $table->string('subject_reference', 64)->index();
            $this->userKeyColumn($table, 'requested_by_user_id', $userKeyType, $userKeyUnsigned);
            $table->string('request_type', 40)->index();
            $table->string('status', 30)->index();
            $table->dateTime('requested_at')->index();
            $table->dateTime('deadline_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->json('results')->nullable();
            $table->text('failure_reason')->nullable();

            $table->foreign('subject_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
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

    private function userKeyColumn(Blueprint $table, string $name, string $type, bool $unsigned): void
    {
        $column = str_contains($type, 'bigint')
            ? $table->bigInteger($name, false, $unsigned)
            : $table->integer($name, false, $unsigned);

        $column->nullable()->index();
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'deactivated_at', 'purge_after', 'deletion_reason', 'deleted_at',
            ]);
        });
    }
};
