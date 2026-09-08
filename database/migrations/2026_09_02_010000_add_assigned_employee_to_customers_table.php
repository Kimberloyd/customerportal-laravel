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
        [$userKeyType, $userKeyUnsigned] = $this->userKeyDefinition();

        if (Schema::hasColumn('customers', 'assigned_employee_id')) {
            // MySQL commits column additions even when the foreign-key clause
            // fails. Normalize that partially added column before retrying.
            Schema::table('customers', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
                $this->userKeyColumn($table, 'assigned_employee_id', $userKeyType, $userKeyUnsigned)
                    ->nullable()
                    ->change();
            });
        } else {
            Schema::table('customers', function (Blueprint $table) use ($userKeyType, $userKeyUnsigned) {
                $this->userKeyColumn($table, 'assigned_employee_id', $userKeyType, $userKeyUnsigned)
                    ->nullable()
                    ->after('user_id');
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys('customers'))->contains(
            fn (array $foreignKey): bool => in_array('assigned_employee_id', $foreignKey['columns'], true),
        );

        if ($hasForeignKey) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign('assigned_employee_id')->references('id')->on('users')->nullOnDelete();
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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_employee_id');
        });
    }
};
