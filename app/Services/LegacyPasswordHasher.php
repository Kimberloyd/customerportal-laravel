<?php

namespace App\Services;

use function Vinsaj9\Crypto\Scrypt\scrypt;

/**
 * Verifies a password against a hash in the Flask app's format
 * (Werkzeug's generate_password_hash()). Handles both formats Werkzeug
 * has defaulted to over its history -- real accounts on the live,
 * years-old database carry both:
 *   - "pbkdf2:sha256:<iterations>$<salt>$<hex digest>" (Werkzeug's
 *     default before 2.3, and still explicitly requestable)
 *   - "scrypt:<n>:<r>:<p>$<salt>$<hex digest>" (Werkzeug's default
 *     since 2.3)
 *
 * This is deliberately NOT registered as the app's default Hasher.
 * Verified against real hashes pulled from the live database -- both
 * byte-for-byte correct against PHP's native hash_pbkdf2() (fast) and
 * a pure-PHP scrypt implementation (no native scrypt extension
 * available; at Werkzeug's default parameters, n=32768, this takes
 * roughly 15 seconds per check). That's fine for the one-time check
 * this class exists for -- verifying an existing user's password
 * exactly once, immediately followed by rehashing to bcrypt so every
 * later login is fast -- but would make login unusable if it were the
 * ongoing verification path.
 */
class LegacyPasswordHasher
{
    public static function isLegacyHash(string $hash): bool
    {
        return str_starts_with($hash, 'scrypt:') || str_starts_with($hash, 'pbkdf2:');
    }

    public static function verify(string $password, string $hash): bool
    {
        if (! self::isLegacyHash($hash)) {
            return false;
        }

        $parts = explode('$', $hash, 3);
        if (count($parts) !== 3) {
            return false;
        }

        [$method, $salt, $expectedHex] = $parts;

        if (str_starts_with($method, 'scrypt:')) {
            return self::verifyScrypt($password, $salt, $expectedHex, $method);
        }

        return self::verifyPbkdf2($password, $salt, $expectedHex, $method);
    }

    private static function verifyScrypt(string $password, string $salt, string $expectedHex, string $method): bool
    {
        $methodParts = explode(':', $method);
        if (count($methodParts) !== 4 || $methodParts[0] !== 'scrypt') {
            return false;
        }

        [, $n, $r, $p] = $methodParts;
        $computedHex = scrypt($password, $salt, (int) $n, (int) $r, (int) $p, 64);

        return hash_equals($expectedHex, $computedHex);
    }

    private static function verifyPbkdf2(string $password, string $salt, string $expectedHex, string $method): bool
    {
        // "pbkdf2:sha256:600000" -> algo=sha256, iterations=600000.
        // Werkzeug also allows a bare "pbkdf2" (defaults to sha256),
        // hence the optional third segment.
        $methodParts = explode(':', $method);
        if ($methodParts[0] !== 'pbkdf2' || count($methodParts) < 2) {
            return false;
        }

        $algo = $methodParts[1];
        $iterations = (int) ($methodParts[2] ?? 150000);

        $computedHex = hash_pbkdf2($algo, $password, $salt, $iterations, 0, false);

        return hash_equals($expectedHex, $computedHex);
    }
}
