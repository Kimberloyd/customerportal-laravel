<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPasswordStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_each_legacy_format(): void
    {
        User::factory()->create(['password_hash' => 'scrypt:16:8:1$salt$digest']);
        User::factory()->create(['password_hash' => 'pbkdf2:sha256:600000$salt$digest']);
        User::factory()->create();

        $this->artisan('security:legacy-passwords')
            ->expectsTable(
                ['Legacy format', 'Accounts remaining'],
                [
                    ['scrypt', 1],
                    ['pbkdf2', 1],
                    ['total', 2],
                ],
            )
            ->expectsOutput('Legacy hashes are upgraded to bcrypt after each successful login.')
            ->assertSuccessful();
    }

    public function test_it_can_fail_a_retirement_gate_while_legacy_hashes_remain(): void
    {
        User::factory()->create(['password_hash' => 'scrypt:16:8:1$salt$digest']);

        $this->artisan('security:legacy-passwords --fail-if-present')->assertFailed();
    }

    public function test_it_confirms_when_the_compatibility_dependency_can_be_removed(): void
    {
        User::factory()->create();

        $this->artisan('security:legacy-passwords --fail-if-present')
            ->expectsOutput('No legacy password hashes remain. The scrypt compatibility dependency can be retired.')
            ->assertSuccessful();
    }
}
