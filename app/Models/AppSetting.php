<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value overrides for settings an administrator can change at runtime,
 * without an .env edit and a redeploy.
 *
 * The contract everywhere is "a missing row means no override": every reader
 * passes the config() value it would otherwise have used as the fallback, so
 * installing this table changes no behaviour until someone actually flips
 * something in the admin UI.
 *
 * Deliberately not cached. These are read on notification sends, which are
 * infrequent, and a cached kill switch is a kill switch that can fail to kill
 * -- a stale entry on a queue worker would keep sending after an admin had
 * turned sending off, which is the exact failure this table exists to prevent.
 */
#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Reads a boolean override, falling back to $default when no row exists.
     *
     * Tolerates the table being absent so that a deploy which runs code before
     * migrations -- or a test booting against a partial schema -- degrades to
     * the config default instead of throwing inside a notification send.
     */
    public static function boolean(string $key, bool $default): bool
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        $value = static::query()->whereKey($key)->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function putBoolean(string $key, bool $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0'],
        );
    }
}
