<?php

namespace App\Console\Commands;

use App\Models\ReleaseNote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('release-notes:remove {version : The version number to remove, as shown on /release-notes}')]
#[Description('Remove a published release note, e.g. after a correction')]
class RemoveReleaseNote extends Command
{
    public function handle(): int
    {
        $version = (int) $this->argument('version');
        $note = ReleaseNote::where('version', $version)->first();

        if (! $note) {
            $this->error("No release note with version {$version}.");

            return self::FAILURE;
        }

        $title = $note->title;
        $note->delete();

        $this->info("Removed release note v{$version}: {$title}");

        return self::SUCCESS;
    }
}
