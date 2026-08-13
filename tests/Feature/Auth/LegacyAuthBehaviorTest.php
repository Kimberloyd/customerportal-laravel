<?php

namespace Tests\Feature\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

use function Vinsaj9\Crypto\Scrypt\scrypt;

class LegacyAuthBehaviorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A small N (16, vs. Werkzeug's real 32768) keeps this fast --
     * see LegacyPasswordHasherTest for the slow, real-N interop proof.
     */
    private function legacyHashFor(string $password): string
    {
        $salt = 'testsalt12345678';
        $hex = scrypt($password, $salt, 16, 8, 1, 64);

        return "scrypt:16:8:1\${$salt}\${$hex}";
    }

    public function test_login_succeeds_with_a_legacy_scrypt_hash_and_rehashes_to_bcrypt(): void
    {
        $user = User::factory()->create([
            'password_hash' => $this->legacyHashFor('CorrectHorseBatteryStaple123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectHorseBatteryStaple123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();
        $this->assertTrue(str_starts_with($user->password_hash, '$2y$'));
        $this->assertTrue(Hash::check('CorrectHorseBatteryStaple123!', $user->password_hash));
    }

    public function test_login_fails_with_wrong_password_against_a_legacy_hash(): void
    {
        $user = User::factory()->create([
            'password_hash' => $this->legacyHashFor('CorrectHorseBatteryStaple123!'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $user->refresh();
        $this->assertFalse(str_starts_with($user->password_hash, '$2y$'));
    }

    public function test_session_version_mismatch_forces_logout_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->get('/dashboard')->assertStatus(200);

        $user->session_version++;
        $user->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_locked_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->assertSame(5, LoginAttempt::where('email', $user->email)->count());

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(5, LoginAttempt::where('email', $user->email)->count());
    }
}
