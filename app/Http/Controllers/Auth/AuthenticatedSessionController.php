<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Matches Flask's "sign out all devices": every session carries
        // the version it was issued under, and SessionVersion middleware
        // rejects the session on any later mismatch (password change, or
        // an explicit sign-out-all-devices action).
        $request->session()->put('session_version', $request->user()->session_version);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Matches Flask's /auth/logout-all: bumps session_version, which
     * invalidates every other session cookie for this user (each one
     * carries the version it was issued under), then logs out locally.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->session_version = $user->session_version + 1;
        $user->save();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
