<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Matches Flask's user_loader checks: every session carries the
 * version it was issued under (stamped at login, see
 * AuthenticatedSessionController::store()), and a mismatch against the
 * user's current session_version means this session has since been
 * superseded by a password change or "sign out all devices" elsewhere.
 * Also matches user_loader's `if not user.is_active: return None` --
 * a deactivated account (Phase 7's admin toggle) must be rejected on
 * its very next request regardless of session_version, not merely
 * have the DB flag flipped with no enforcement anywhere.
 */
class EnsureSessionVersionMatches
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (! $user->is_active || $request->session()->get('session_version') !== $user->session_version)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
