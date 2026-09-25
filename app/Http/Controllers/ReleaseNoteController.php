<?php

namespace App\Http\Controllers;

use App\Models\ReleaseNote;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ReleaseNotes', [
            'releases' => ReleaseNote::orderByDesc('version')
                ->get(['version', 'title', 'body', 'published_at']),
        ]);
    }
}
