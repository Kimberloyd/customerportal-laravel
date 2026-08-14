<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Matches the Flask app's `users` table exactly (see the migration for
// the full column list/rationale) -- no `name`/`password`/
// `email_verified_at`/`remember_token`/`updated_at`, since Flask's User
// model never had them.
#[Fillable(['full_name', 'email', 'role', 'is_active', 'profile_image', 'password_hash', 'session_version'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'session_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Authenticatable expects a `password` column by default -- ours is
     * `password_hash`. This is the one method Auth::attempt()/the
     * configured Hasher actually read the credential from.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * There is no remember_token column on this table (see the users
     * migration) -- Authenticatable's default setRememberToken() would
     * try to persist one and crash with an "unknown column" error the
     * moment anyone checks "Remember Me" on login. No-op it instead: a
     * checked "Remember Me" degrades to a normal session-lifetime login
     * rather than a persistent one.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }
}
