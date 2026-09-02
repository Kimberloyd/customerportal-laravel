<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Team extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }
}
