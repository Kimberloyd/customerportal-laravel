<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Shared date-range primitives used by both DashboardController and
 * ReportController -- extracted from DashboardController's original
 * private methods once a second controller needed the exact same
 * month-arithmetic/custom-date-parsing logic.
 */
class ReportPeriod
{
    public static function monthsAgoStart(CarbonImmutable $current, int $monthsAgo): CarbonImmutable
    {
        $monthIndex = $current->year * 12 + $current->month - 1 - $monthsAgo;
        $year = intdiv($monthIndex, 12);
        $zeroBasedMonth = $monthIndex % 12;
        if ($zeroBasedMonth < 0) {
            $zeroBasedMonth += 12;
            $year--;
        }

        return CarbonImmutable::create($year, $zeroBasedMonth + 1, 1, 0, 0, 0, 'UTC');
    }

    /**
     * Carbon::createFromFormat() throws on malformed input rather than
     * returning false (unlike PHP's native DateTime::createFromFormat)
     * -- this normalizes that to null so callers can fall back the same
     * way Flask's `except ValueError` does for bad custom-range input.
     */
    public static function parseDateOrNull(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date ?: null;
    }
}
