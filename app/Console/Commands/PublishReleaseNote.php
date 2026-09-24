<?php

namespace App\Console\Commands;

use App\Models\ReleaseNote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

// Release notes are authored by whoever ships the change (an engineer, or
// Claude working the codebase), not typed in by an admin through a web
// form -- this command is the only way a release note gets created.
#[Signature('release-notes:publish {title : Short headline for this release} {--note=* : One bullet line per --note flag; at least one required}')]
#[Description('Publish a release note, visible on /whats-new, for a change just shipped')]
class PublishReleaseNote extends Command
{
    public function handle(): int
    {
        $title = trim((string) $this->argument('title'));
        $notes = array_values(array_filter(array_map('trim', (array) $this->option('note'))));

        if ($title === '') {
            $this->error('Title cannot be empty.');

            return self::FAILURE;
        }

        if ($notes === []) {
            $this->error('Provide at least one --note="..." bullet line.');

            return self::FAILURE;
        }

        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(),
            'title' => $title,
            'body' => implode("\n", $notes),
            'published_at' => now(),
            'created_by' => null,
        ]);

        $this->info("Published release note v{$note->version}: {$note->title}");

        return self::SUCCESS;
    }
}
