<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_totp_matches_the_rfc_6238_sha1_test_secret(): void
    {
        $twoFactor = app(TwoFactorAuthentication::class);

        $this->assertSame('287082', $twoFactor->code(self::RFC_SECRET, 59));
        $this->assertTrue($twoFactor->verify(self::RFC_SECRET, '287082', 59));
        $this->assertFalse($twoFactor->verify(self::RFC_SECRET, '287083', 59));
    }

    public function test_account_with_two_factor_enabled_is_challenged_after_password(): void
    {
        $user = $this->twoFactorUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('two_factor_login.user_id', $user->id);
    }

    public function test_valid_authenticator_code_completes_login(): void
    {
        $user = $this->twoFactorUser();
        $code = app(TwoFactorAuthentication::class)->code(self::RFC_SECRET);

        $response = $this->withSession([
            'two_factor_login.user_id' => $user->id,
            'two_factor_login.remember' => false,
        ])->post('/two-factor-challenge', ['code' => $code]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('session_version', $user->session_version);
        $response->assertSessionMissing('two_factor_login');
    }

    public function test_invalid_authenticator_code_does_not_complete_login(): void
    {
        $user = $this->twoFactorUser();

        $this->withSession(['two_factor_login.user_id' => $user->id])
            ->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_recovery_code_can_only_be_used_once(): void
    {
        $plainCode = 'abcde-12345';
        $user = $this->twoFactorUser([Hash::make('abcde12345')]);

        $this->withSession(['two_factor_login.user_id' => $user->id])
            ->post('/two-factor-challenge', ['code' => $plainCode])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->refresh()->two_factor_recovery_codes);

        auth()->logout();
        $this->withSession(['two_factor_login.user_id' => $user->id])
            ->post('/two-factor-challenge', ['code' => $plainCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_confirming_setup_encrypts_the_secret_and_hashes_recovery_codes(): void
    {
        $user = User::factory()->create();
        $code = app(TwoFactorAuthentication::class)->code(self::RFC_SECRET);

        $response = $this->actingAsUser($user)
            ->withSession(['two_factor_setup_secret' => self::RFC_SECRET])
            ->post('/settings/two-factor/confirm', ['code' => $code]);

        $response->assertRedirect()->assertSessionHas('two_factor_recovery_codes');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorAuthentication());
        $this->assertSame(self::RFC_SECRET, $user->two_factor_secret);
        $this->assertCount(8, $user->two_factor_recovery_codes);

        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString(self::RFC_SECRET, $raw->two_factor_secret);
        foreach (session('two_factor_recovery_codes') as $plainCode) {
            $this->assertStringNotContainsString($plainCode, $raw->two_factor_recovery_codes);
        }
    }

    public function test_recovery_code_can_disable_two_factor_authentication(): void
    {
        $user = $this->twoFactorUser([Hash::make('abcde12345')]);

        $this->actingAsUser($user)
            ->delete('/settings/two-factor', ['code' => 'abcde-12345'])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorAuthentication());
        $this->assertNull($user->two_factor_recovery_codes);
    }

    private function twoFactorUser(array $recoveryCodes = []): User
    {
        return User::factory()->create([
            'two_factor_secret' => self::RFC_SECRET,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
