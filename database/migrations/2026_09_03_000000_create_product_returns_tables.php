<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $this->reference($table, 'purchase_order_id', 'purchase_orders');
            $this->reference($table, 'customer_id', 'customers');
            // Return history remains with the order if an account is erased; the
            // requester identity is anonymized by setting this reference to null.
            $this->reference($table, 'requested_by_user_id', 'users', nullable: true);
            $table->string('status', 20)->default('requested');
            $table->string('reason', 1000);
            $table->string('review_note', 1000)->nullable();
            $table->dateTime('requested_at');
            $table->dateTime('reviewed_at')->nullable();
            $this->reference($table, 'reviewed_by_user_id', 'users', nullable: true);
            $table->dateTime('received_at')->nullable();
            $this->reference($table, 'received_by_user_id', 'users', nullable: true);

            $table->index(['purchase_order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('product_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_return_id')->constrained('product_returns')->cascadeOnDelete();
            $this->reference($table, 'purchase_order_item_id', 'purchase_order_items');
            $table->unsignedInteger('quantity');

            $table->unique(['product_return_id', 'purchase_order_item_id'], 'product_return_items_return_order_item_unique');
        });
    }

    private function reference(Blueprint $table, string $name, string $referencedTable, bool $nullable = false): void
    {
        // Imported Flask databases use signed INT IDs; fresh Laravel databases
        // use BIGINT IDs. Foreign keys must match both size and signedness.
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
        if ($nullable) {
            $foreign->nullOnDelete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_return_items');
        Schema::dropIfExists('product_returns');
    }
};
