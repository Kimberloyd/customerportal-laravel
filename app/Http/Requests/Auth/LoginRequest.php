<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\LegacyPasswordHasher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Lockout and attempt history are backed by the login_attempts table
     * (shared with the Flask app), not Laravel's cache-based RateLimiter
     * -- same threshold/window, same table, so a lockout triggered by one
     * app is honored by the other during the migration.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $email = $this->string('email')->toString();

        $this->ensureIsNotRateLimited($email);

        $user = User::where('email', $email)->first();
        $authenticated = $this->attemptAuthentication($user);

        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $this->ip(),
            'successful' => $authenticated,
            'created_at' => now(),
        ]);

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }
    }

    /**
     * Laravel's default bcrypt Hasher::check() throws a RuntimeException
     * for any hash that isn't bcrypt -- it does not return false -- so
     * Auth::attempt() can't be tried first and fallen back from the way
     * "try bcrypt, then legacy" would suggest. The stored hash's format
     * has to be checked before deciding which path even *can* run.
     * Once a user's hash has been rehashed below, they permanently take
     * the fast bcrypt path on every future login.
     */
    private function attemptAuthentication(?User $user): bool
    {
        if ($user && ! $user->is_active) {
            return false;
        }

        if ($user && LegacyPasswordHasher::isLegacyHash($user->password_hash)) {
            return $this->attemptLegacyAuthentication($user);
        }

        if (Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->session()->regenerate();

            return true;
        }

        return false;
    }

    private function attemptLegacyAuthentication(User $user): bool
    {
        if (! LegacyPasswordHasher::verify($this->string('password')->toString(), $user->password_hash)) {
            return false;
        }

        $user->password_hash = Hash::make($this->string('password')->toString());
        $user->save();

        Auth::login($user, $this->boolean('remember'));
        $this->session()->regenerate();

        return true;
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(string $email): void
    {
        $threshold = config('login_lockout.threshold');
        $windowMinutes = config('login_lockout.window_minutes');

        $recentFailures = LoginAttempt::where('email', $email)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentFailures < $threshold) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $windowMinutes * 60,
                'minutes' => $windowMinutes,
            ]),
        ]);
    }
}
