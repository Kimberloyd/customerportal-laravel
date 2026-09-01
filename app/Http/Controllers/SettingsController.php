<?php

namespace App\Http\Controllers;

use App\Support\AdminUserListing;
use App\Support\UserAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service account settings. Deliberately limited to full_name and
 * phone -- email and password stay staff-managed, matching the design
 * note in routes/auth.php (no self-service email/password changes).
 */
class SettingsController extends Controller
{
    public function edit(): Response
    {
        $user = Auth::user();

        return Inertia::render('Settings/Edit', [
            'user' => [
                'full_name' => $user->full_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role_label' => AdminUserListing::ROLE_LABELS[$user->role] ?? $user->role,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $fullName = trim((string) $request->input('full_name', ''));
        $phone = trim((string) $request->input('phone', ''));

        if ($fullName === '') {
            throw ValidationException::withMessages(['full_name' => 'Enter a full name.']);
        }

        DB::transaction(function () use ($user, $fullName, $phone, $request) {
            $user->full_name = $fullName;
            $user->phone = $phone !== '' ? $phone : null;
            $user->save();

            UserAudit::record($user, 'updated', 'full name/phone updated via account settings', $request);
        });

        return back()->with('success', 'Settings saved.');
    }
}
