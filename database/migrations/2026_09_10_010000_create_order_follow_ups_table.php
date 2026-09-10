<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_follow_ups', function (Blueprint $table) {
            $table->id();
            $this->reference($table, 'purchase_order_id', 'purchase_orders');
            $this->reference($table, 'product_return_id', 'product_returns', nullable: true);
            $table->string('kind', 40);
            $table->unsignedInteger('cycle')->default(1);
            $table->string('level', 20)->default('reminder');
            $table->string('status', 20)->default('pending');
            $table->dateTime('triggered_at');
            $table->dateTime('next_due_at')->nullable();
            $table->dateTime('last_dispatched_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('last_error_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'kind', 'cycle'], 'order_follow_ups_order_kind_cycle_unique');
            $table->index(['status', 'next_due_at']);
            $table->index(['product_return_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_follow_ups');
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
        $column->nullable($nullable);
        $foreign = $table->foreign($name)->references('id')->on($referencedTable);
        $nullable ? $foreign->nullOnDelete() : $foreign->cascadeOnDelete();
    }
};
