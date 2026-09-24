<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\ReleaseNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReleaseNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $values = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = ReleaseNote::create([
            'version' => ReleaseNote::nextVersion(),
            'title' => trim($values['title']),
            'body' => trim($values['body']),
            'published_at' => now(),
            'created_by' => Auth::id(),
        ]);

        $this->recordAudit($request, $note->id, 'published', "release note v{$note->version}: {$note->title}");

        return redirect()->route('admin.dashboard', ['tab' => 'release-notes'])->with('success', 'Release note published.');
    }

    public function destroy(Request $request, ReleaseNote $releaseNote): RedirectResponse
    {
        $this->requireAdmin();

        $version = $releaseNote->version;
        $title = $releaseNote->title;
        $id = $releaseNote->id;
        $releaseNote->delete();

        $this->recordAudit($request, $id, 'deleted', "release note v{$version} deleted: {$title}");

        return redirect()->route('admin.dashboard', ['tab' => 'release-notes'])->with('success', 'Release note removed.');
    }

    private function recordAudit(Request $request, int $entityId, string $action, string $details): void
    {
        AdminAudit::create([
            'entity_type' => 'release_note', 'entity_id' => $entityId, 'action' => $action,
            'details' => $details,
            'actor_user_id' => Auth::id(), 'actor_role' => 'admin',
            'ip_address' => $request->ip(), 'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function requireAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
}
