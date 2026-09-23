<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['purchase_order_notification_id', 'user_id', 'read_at'])]
class NotificationRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
