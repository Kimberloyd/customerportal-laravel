<?php

namespace Tests\Feature\Admin;

use App\Models\ReleaseNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReleaseNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_command_publishes_a_release_note(): void
    {
        $this->artisan('release-notes:publish', [
            'title' => 'Archived orders',
            '--note' => ['Open an archived order to view or restore it', 'Fixed search missing recent orders'],
        ])->assertSuccessful();

        $note = ReleaseNote::where('title', 'Archived orders')->firstOrFail();
        $this->assertSame(1, $note->version);
        $this->assertNull($note->created_by);
        $this->assertNotNull($note->public_id);
        $this->assertSame(
            "Open an archived order to view or restore it\nFixed search missing recent orders",
            $note->body,
        );
    }

    public function test_versions_auto_increment_across_publishes(): void
    {
        $this->artisan('release-notes:publish', ['title' => 'First', '--note' => ['One']]);
        $this->artisan('release-notes:publish', ['title' => 'Second', '--note' => ['Two']]);

        $this->assertSame(1, ReleaseNote::where('title', 'First')->value('version'));
        $this->assertSame(2, ReleaseNote::where('title', 'Second')->value('version'));
    }

    public function test_publish_command_requires_at_least_one_note(): void
    {
        $this->artisan('release-notes:publish', ['title' => 'Empty'])->assertFailed();

        $this->assertDatabaseMissing('release_notes', ['title' => 'Empty']);
    }

    public function test_console_command_removes_a_release_note(): void
    {
        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(), 'title' => 'Old note', 'body' => 'Something',
            'published_at' => now(),
        ]);

        $this->artisan('release-notes:remove', ['version' => $note->version])->assertSuccessful();

        $this->assertDatabaseMissing('release_notes', ['id' => $note->id]);
    }

    public function test_remove_command_fails_for_an_unknown_version(): void
    {
        $this->artisan('release-notes:remove', ['version' => 999])->assertFailed();
    }

    public function test_there_is_no_admin_web_page_for_release_notes(): void
    {
        $this->assertFalse(Route::has('admin.release-notes.index'));
    }
}
