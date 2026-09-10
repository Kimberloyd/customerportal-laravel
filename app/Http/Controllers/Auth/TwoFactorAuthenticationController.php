<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use App\Support\UserAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor_login.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = User::query()
            ->whereKey($request->session()->get('two_factor_login.user_id'))
            ->where('is_active', true)
            ->first();

        if (! $user || ! $user->hasTwoFactorAuthentication()) {
            $request->session()->forget('two_factor_login');

            return redirect()->route('login')->withErrors(['email' => 'Sign in again to continue.']);
        }

        $code = $request->string('code')->toString();
        if (! $twoFactor->verify($user->two_factor_secret, $code)) {
            $user = DB::transaction(function () use ($user, $code, $twoFactor, $request) {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $recoveryIndex = $this->matchingRecoveryCodeIndex($lockedUser, $code, $twoFactor);

                if ($recoveryIndex === null) {
                    throw ValidationException::withMessages(['code' => 'Enter a valid authentication or recovery code.']);
                }

                $codes = $lockedUser->two_factor_recovery_codes;
                unset($codes[$recoveryIndex]);
                $lockedUser->two_factor_recovery_codes = array_values($codes);
                $lockedUser->save();
                UserAudit::record($lockedUser, 'two_factor_recovery_used', 'one recovery code consumed during sign in', $request);

                return $lockedUser;
            });
        }

        $remember = (bool) $request->session()->get('two_factor_login.remember', false);
        $request->session()->forget('two_factor_login');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('session_version', $user->session_version);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function beginSetup(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        abort_if($request->user()->hasTwoFactorAuthentication(), 409);

        $request->session()->put('two_factor_setup_secret', $twoFactor->generateSecret());

        return back();
    }

    public function confirmSetup(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $secret = (string) $request->session()->get('two_factor_setup_secret');

        if ($secret === '') {
            throw ValidationException::withMessages(['code' => 'Start two-factor setup again.']);
        }

        if (! $twoFactor->verify($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'That authentication code is not valid. Try the current code from your app.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $user = $request->user();

        DB::transaction(function () use ($user, $secret, $recoveryCodes, $request, $twoFactor) {
            $user->two_factor_secret = $secret;
            $user->two_factor_recovery_codes = array_map(
                fn (string $code): string => Hash::make($twoFactor->normalizeRecoveryCode($code)),
                $recoveryCodes,
            );
            $user->two_factor_confirmed_at = now();
            $user->session_version++;
            $user->save();
            UserAudit::record($user, 'two_factor_enabled', 'two-factor authentication enabled', $request);
        });

        $request->session()->forget('two_factor_setup_secret');
        $request->session()->put('session_version', $user->session_version);

        return back()->with('two_factor_recovery_codes', $recoveryCodes)
            ->with('success', 'Two-factor authentication is now enabled.');
    }

    public function cancelSetup(Request $request): RedirectResponse
    {
        $request->session()->forget('two_factor_setup_secret');

        return back();
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $this->validateCurrentCode($request, $twoFactor);
        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $user = $request->user();
        $user->two_factor_recovery_codes = array_map(
            fn (string $code): string => Hash::make($twoFactor->normalizeRecoveryCode($code)),
            $recoveryCodes,
        );
        $user->save();
        UserAudit::record($user, 'two_factor_recovery_regenerated', 'two-factor recovery codes regenerated', $request);

        return back()->with('two_factor_recovery_codes', $recoveryCodes)
            ->with('success', 'New recovery codes generated. Previous codes no longer work.');
    }

    public function disable(Request $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $this->validateCurrentCode($request, $twoFactor);
        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $user->two_factor_secret = null;
            $user->two_factor_recovery_codes = null;
            $user->two_factor_confirmed_at = null;
            $user->session_version++;
            $user->save();
            UserAudit::record($user, 'two_factor_disabled', 'two-factor authentication disabled', $request);
        });

        $request->session()->put('session_version', $user->session_version);

        return back()->with('success', 'Two-factor authentication is now disabled.');
    }

    private function validateCurrentCode(Request $request, TwoFactorAuthentication $twoFactor): void
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();

        if (! $user->hasTwoFactorAuthentication()) {
            abort(409);
        }

        $code = $request->string('code')->toString();
        if ($twoFactor->verify($user->two_factor_secret, $code)) {
            return;
        }

        DB::transaction(function () use ($user, $code, $twoFactor) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $recoveryIndex = $this->matchingRecoveryCodeIndex($lockedUser, $code, $twoFactor);
            if ($recoveryIndex === null) {
                throw ValidationException::withMessages(['code' => 'Enter the current authentication code or an unused recovery code.']);
            }

            $codes = $lockedUser->two_factor_recovery_codes;
            unset($codes[$recoveryIndex]);
            $lockedUser->two_factor_recovery_codes = array_values($codes);
            $lockedUser->save();
        });
    }

    private function matchingRecoveryCodeIndex(User $user, string $code, TwoFactorAuthentication $twoFactor): ?int
    {
        $normalized = $twoFactor->normalizeRecoveryCode($code);
        if ($normalized === '') {
            return null;
        }

        foreach ($user->two_factor_recovery_codes ?? [] as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                return $index;
            }
        }

        return null;
    }
}
