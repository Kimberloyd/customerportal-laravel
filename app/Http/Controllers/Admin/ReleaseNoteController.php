<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\ReleaseNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// No store() here on purpose -- release notes are published by whoever
// ships the change via `php artisan release-notes:publish`, not typed in
// through this admin page. Admins can only review and remove entries here.
class ReleaseNoteController extends Controller
{
    public function index(): Response
    {
        $this->requireAdmin();

        return Inertia::render('Admin/ReleaseNotes', [
            'releaseNotes' => ReleaseNote::orderByDesc('version')
                ->get(['public_id', 'version', 'title', 'body', 'published_at']),
        ]);
    }

    public function destroy(Request $request, ReleaseNote $releaseNote): RedirectResponse
    {
        $this->requireAdmin();

        $version = $releaseNote->version;
        $title = $releaseNote->title;
        $id = $releaseNote->id;
        $releaseNote->delete();

        $this->recordAudit($request, $id, 'deleted', "release note v{$version} deleted: {$title}");

        return redirect()->route('admin.release-notes.index')->with('success', 'Release note removed.');
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
