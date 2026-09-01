<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('security:legacy-passwords {--fail-if-present : Exit unsuccessfully while legacy hashes remain}')]
#[Description('Report accounts that still require Werkzeug password migration')]
class LegacyPasswordStatus extends Command
{
    public function handle(): int
    {
        $counts = [
            'scrypt' => User::withTrashed()->where('password_hash', 'like', 'scrypt:%')->count(),
            'pbkdf2' => User::withTrashed()->where('password_hash', 'like', 'pbkdf2:%')->count(),
        ];
        $total = array_sum($counts);

        $this->table(
            ['Legacy format', 'Accounts remaining'],
            [
                ['scrypt', $counts['scrypt']],
                ['pbkdf2', $counts['pbkdf2']],
                ['total', $total],
            ],
        );

        if ($total === 0) {
            $this->info('No legacy password hashes remain. The scrypt compatibility dependency can be retired.');

            return self::SUCCESS;
        }

        $this->warn('Legacy hashes are upgraded to bcrypt after each successful login.');

        return $this->option('fail-if-present') ? self::FAILURE : self::SUCCESS;
    }
}
