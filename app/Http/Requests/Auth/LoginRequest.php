<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
     * (a real, persistent table, not Laravel's cache-based RateLimiter) so
     * lockout state survives a cache flush or an app restart.
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

    private function attemptAuthentication(?User $user): bool
    {
        if ($user && ! $user->is_active) {
            return false;
        }

        if (Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->session()->regenerate();

            return true;
        }

        return false;
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
