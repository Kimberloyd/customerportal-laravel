<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['version', 'title', 'body', 'published_at', 'created_by'])]
class ReleaseNote extends Model
{
    use HasPublicId;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextVersion(): int
    {
        return (int) (static::max('version') ?? 0) + 1;
    }
}
