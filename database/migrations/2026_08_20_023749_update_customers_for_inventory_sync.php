<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer company_name/channel now come live from the inventoryapp
 * customers API (see App\Support\InventoryApiClient::allCustomers()
 * and the customers:sync command) instead of manual entry. The API
 * doesn't provide contact_person/email/phone/address, so those columns
 * are dropped -- customer_code, is_active, and user_id (portal login
 * linking) stay local-only since the API never supplied them anyway.
 *
 * Like 2026_08_18_000000_drop_products_table.php, this only ever runs
 * against the local/dev database (DEPLOY.md forbids `php artisan
 * migrate` against the real shared production database, which this
 * app never owns or manages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('external_id')->nullable()->unique()->after('id');
            $table->dropColumn(['contact_person', 'email', 'phone', 'address']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('external_id');
            $table->string('contact_person', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
        });
    }
};
