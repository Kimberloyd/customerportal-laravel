<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_returns') && ! Schema::hasColumn('product_returns', 'attachment_files')) {
            Schema::table('product_returns', function (Blueprint $table) {
                $table->json('attachment_files')->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_returns') && Schema::hasColumn('product_returns', 'attachment_files')) {
            Schema::table('product_returns', function (Blueprint $table) {
                $table->dropColumn('attachment_files');
            });
        }

        // Supports local environments that applied the earlier, unreleased
        // single-attachment version of this migration.
        if (Schema::hasTable('product_returns') && Schema::hasColumn('product_returns', 'attachment_file')) {
            Schema::table('product_returns', function (Blueprint $table) {
                $table->dropColumn('attachment_file');
            });
        }
    }
};
