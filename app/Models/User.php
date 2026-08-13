<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Matches the Flask app's `users` table exactly (see the migration for
// the full column list/rationale) -- no `name`/`password`/
// `email_verified_at`/`remember_token`/`updated_at`, since Flask's User
// model never had them.
#[Fillable(['full_name', 'email', 'role', 'is_active', 'profile_image'])]
#[Hidden(['password_hash'])]
class User extends Authenticatable
{
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

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }
}
