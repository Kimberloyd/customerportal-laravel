<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempCreateOrderDebugTest extends TestCase
{
    public function test_create_order_page_loads(): void
    {
        $user = User::first();
        $response = $this->actingAs($user)->get('/purchase-orders/create');

        $response->dump();
        $response->assertStatus(200);
    }
}
