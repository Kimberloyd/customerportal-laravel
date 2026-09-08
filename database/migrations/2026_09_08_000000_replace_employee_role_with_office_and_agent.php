<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'office', 'agent', 'employee', 'customer') NOT NULL DEFAULT 'customer'");
        }

        DB::table('users')->where('role', 'employee')->update(['role' => 'agent']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'office', 'agent', 'customer') NOT NULL DEFAULT 'customer'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'office', 'agent', 'employee', 'customer') NOT NULL DEFAULT 'customer'");
        }

        DB::table('users')->whereIn('role', ['office', 'agent'])->update(['role' => 'employee']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'employee', 'customer') NOT NULL DEFAULT 'customer'");
        }
    }
};
