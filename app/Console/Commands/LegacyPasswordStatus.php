<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('security:legacy-passwords {--fail-if-present : Exit unsuccessfully while legacy hashes remain} {--quiet-log : Skip console output, only write the structured log line}')]
#[Description('Report accounts that still require Werkzeug password migration')]
class LegacyPasswordStatus extends Command
{
    public function handle(): int
    {
        $counts = [
            'scrypt' => User::withTrashed()->where('password_hash', 'like', 'scrypt:%')->count(),
            'pbkdf2' => User::withTrashed()->where('password_hash', 'like', 'pbkdf2:%')->count(),
        ];
        $legacyTotal = array_sum($counts);
        $accountTotal = User::withTrashed()->count();
        $coveragePercent = $accountTotal > 0
            ? round((($accountTotal - $legacyTotal) / $accountTotal) * 100, 1)
            : 100.0;

        // A structured log line (not just console output) is what makes
        // Phase 1 of the Flask decoupling plan "measurable, not assumed" --
        // a coverage number that only appears when someone remembers to run
        // this by hand doesn't demonstrate a trend. See the weekly
        // schedule in routes/console.php.
        Log::info('legacy_password_coverage', [
            'legacy_scrypt' => $counts['scrypt'],
            'legacy_pbkdf2' => $counts['pbkdf2'],
            'legacy_total' => $legacyTotal,
            'account_total' => $accountTotal,
            'bcrypt_coverage_percent' => $coveragePercent,
        ]);

        if ($this->option('quiet-log')) {
            return $legacyTotal === 0 || ! $this->option('fail-if-present') ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Legacy format', 'Accounts remaining'],
            [
                ['scrypt', $counts['scrypt']],
                ['pbkdf2', $counts['pbkdf2']],
                ['total', $legacyTotal],
            ],
        );
        $this->line("bcrypt coverage: {$coveragePercent}% ({$accountTotal} account(s) total)");

        if ($legacyTotal === 0) {
            $this->info('No legacy password hashes remain. The scrypt compatibility dependency can be retired.');

            return self::SUCCESS;
        }

        $this->warn('Legacy hashes are upgraded to bcrypt after each successful login.');

        return $this->option('fail-if-present') ? self::FAILURE : self::SUCCESS;
    }
}
