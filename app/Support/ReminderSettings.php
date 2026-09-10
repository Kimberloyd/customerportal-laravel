<?php

namespace App\Support;

use App\Models\AppSetting;

class ReminderSettings
{
    public const ENABLED_KEY = 'order_reminders.enabled';

    public const SMS_ENABLED_KEY = 'order_reminders.customer_sms_enabled';

    public const QUIET_START_KEY = 'order_reminders.quiet_hours_start';

    public const QUIET_END_KEY = 'order_reminders.quiet_hours_end';

    public static function enabled(): bool
    {
        return AppSetting::boolean(self::ENABLED_KEY, (bool) config('reminders.enabled', false));
    }

    public static function customerSmsEnabled(): bool
    {
        return AppSetting::boolean(self::SMS_ENABLED_KEY, (bool) config('reminders.customer_sms_enabled', false));
    }

    public static function timezone(): string
    {
        return (string) config('reminders.timezone', 'Asia/Manila');
    }

    public static function quietStart(): int
    {
        return AppSetting::integer(self::QUIET_START_KEY, (int) config('reminders.quiet_hours_start', 20));
    }

    public static function quietEnd(): int
    {
        return AppSetting::integer(self::QUIET_END_KEY, (int) config('reminders.quiet_hours_end', 8));
    }

    public static function threshold(string $kind, string $level): int
    {
        $default = (int) config("reminders.thresholds.{$kind}.{$level}", 24);

        return AppSetting::integer("order_reminders.thresholds.{$kind}.{$level}", $default);
    }

    /** @return array<int, string> */
    public static function levels(string $kind): array
    {
        return array_keys((array) config("reminders.thresholds.{$kind}", []));
    }

    public static function settingsPayload(): array
    {
        $thresholds = [];
        foreach ((array) config('reminders.thresholds', []) as $kind => $levels) {
            foreach (array_keys($levels) as $level) {
                $thresholds[$kind][$level] = self::threshold($kind, $level);
            }
        }

        return [
            'enabled' => self::enabled(),
            'customer_sms_enabled' => self::customerSmsEnabled(),
            'timezone' => self::timezone(),
            'quiet_hours_start' => self::quietStart(),
            'quiet_hours_end' => self::quietEnd(),
            'thresholds' => $thresholds,
            'last_scheduler_run' => AppSetting::string('order_reminders.last_scheduler_run'),
            'last_failure' => AppSetting::string('order_reminders.last_failure'),
        ];
    }
}
