<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Plain actingAs() doesn't know about this app's session_version
     * convention (stamped at real login, see
     * AuthenticatedSessionController::store()) -- EnsureSessionVersionMatches
     * would otherwise see a session with no session_version at all,
     * treat that as a mismatch against the user's real value, and force
     * a logout on the very next request. Use this in place of bare
     * actingAs() for any test that needs to get past that middleware.
     */
    protected function actingAsUser(User $user, ?string $guard = null): static
    {
        $this->withSession(['session_version' => $user->session_version]);

        return $this->actingAs($user, $guard);
    }
}
