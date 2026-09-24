<?php

namespace Tests\Feature\Admin;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_a_release_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/release-notes', [
            'title' => 'Archived orders',
            'body' => "Open an archived order to view or restore it\nFixed search missing recent orders",
        ])->assertRedirect(route('admin.dashboard', ['tab' => 'release-notes']));

        $note = ReleaseNote::where('title', 'Archived orders')->firstOrFail();
        $this->assertSame(1, $note->version);
        $this->assertSame($admin->id, $note->created_by);
        $this->assertNotNull($note->public_id);
        $this->assertDatabaseHas('admin_audits', [
            'entity_type' => 'release_note',
            'entity_id' => $note->id,
            'action' => 'published',
        ]);
    }

    public function test_versions_auto_increment_across_publishes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/release-notes', ['title' => 'First', 'body' => 'One']);
        $this->actingAsUser($admin)->post('/admin/release-notes', ['title' => 'Second', 'body' => 'Two']);

        $this->assertSame(1, ReleaseNote::where('title', 'First')->value('version'));
        $this->assertSame(2, ReleaseNote::where('title', 'Second')->value('version'));
    }

    public function test_title_and_body_are_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->post('/admin/release-notes', [
            'title' => '', 'body' => '',
        ])->assertSessionHasErrors(['title', 'body']);
    }

    public function test_admin_can_delete_a_release_note(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(), 'title' => 'Old note', 'body' => 'Something',
            'published_at' => now(), 'created_by' => $admin->id,
        ]);

        $this->actingAsUser($admin)
            ->delete(route('admin.release-notes.destroy', $note))
            ->assertRedirect(route('admin.dashboard', ['tab' => 'release-notes']));

        $this->assertDatabaseMissing('release_notes', ['id' => $note->id]);
    }

    public function test_non_admin_cannot_publish_or_delete_release_notes(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);
        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(), 'title' => 'Protected', 'body' => 'Something',
            'published_at' => now(),
        ]);

        $this->actingAsUser($agent)->post('/admin/release-notes', [
            'title' => 'No access', 'body' => 'Nope',
        ])->assertForbidden();

        $this->actingAsUser($agent)
            ->delete(route('admin.release-notes.destroy', $note))
            ->assertForbidden();

        $this->assertDatabaseHas('release_notes', ['id' => $note->id]);
    }
}
