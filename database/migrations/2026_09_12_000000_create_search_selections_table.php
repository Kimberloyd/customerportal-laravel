<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_selections', function (Blueprint $table) {
            $table->id();
            // users.id is a plain signed int (not Laravel's default
            // bigint-unsigned) in this shared-with-Flask database, so
            // foreignId() -- which always generates bigint unsigned --
            // fails to constrain against it. Match the real column type.
            $table->integer('user_id');
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

    public function down(): void
    {
        Schema::dropIfExists('search_selections');
    }
};
