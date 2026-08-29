<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'subject_user_id', 'subject_reference', 'requested_by_user_id',
    'request_type', 'status', 'requested_at', 'deadline_at',
    'completed_at', 'results', 'failure_reason',
])]
class DataSubjectRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'deadline_at' => 'datetime',
            'completed_at' => 'datetime',
            'results' => 'array',
        ];
    }
}
