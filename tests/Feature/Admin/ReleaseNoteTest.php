<?php

namespace Tests\Feature\Admin;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_command_requires_at_least_one_note(): void
    {
        $this->artisan('release-notes:publish', ['title' => 'Empty'])->assertFailed();

        $this->assertDatabaseMissing('release_notes', ['title' => 'Empty']);
    }

    public function test_admin_can_delete_a_release_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(), 'title' => 'Old note', 'body' => 'Something',
            'published_at' => now(),
        ]);

        $this->actingAsUser($admin)
            ->delete(route('admin.release-notes.destroy', $note))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'release-notes']));

        $this->assertDatabaseMissing('release_notes', ['id' => $note->id]);
        $this->assertDatabaseHas('admin_audits', [
            'entity_type' => 'release_note',
            'entity_id' => $note->id,
            'action' => 'deleted',
        ]);
    }

    public function test_non_admin_cannot_delete_a_release_note(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(), 'title' => 'Protected', 'body' => 'Something',
            'published_at' => now(),
        ]);

        $this->actingAsUser($agent)
            ->delete(route('admin.release-notes.destroy', $note))
            ->assertForbidden();

        $this->assertDatabaseHas('release_notes', ['id' => $note->id]);
    }

    public function test_there_is_no_web_route_to_create_a_release_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/release-notes', [
            'title' => 'Should not work', 'body' => 'Nope',
        ])->assertNotFound();
    }
}
