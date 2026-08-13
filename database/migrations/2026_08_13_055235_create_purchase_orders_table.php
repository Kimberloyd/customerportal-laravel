<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 100)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('po_file', 255)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->text('remarks')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });

        // SQLite's ALTER TABLE has no ADD CONSTRAINT support -- this only
        // runs against the real shared MySQL database.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE purchase_orders ADD CONSTRAINT ck_purchase_orders_status "
                . "CHECK (status IN ('submitted','partial','processing','completed','cancelled'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
