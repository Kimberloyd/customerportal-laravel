<?php

namespace App\Http\Controllers;

use App\Models\AdminAudit;
use App\Models\AppSetting;
use App\Support\AdminUserListing;
use App\Support\OrderNotifications;
use App\Support\SemaphoreSms;
use App\Support\UserAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            // Only admins get the integration panel, so only they get its state.
            'sms' => $user->role === 'admin'
                ? [
                    'enabled' => OrderNotifications::smsEnabled(),
                    'configured' => SemaphoreSms::isConfigured(),
                ]
                : null,
        ]);
    }

    /**
     * Admin-only runtime kill switch for outbound Semaphore SMS. Exists so
     * testing can be stopped from burning SMS credits without an .env edit and
     * a redeploy -- see App\Models\AppSetting for the override contract.
     */
    public function updateSms(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        abort_unless($user->role === 'admin', 403);

        $enabled = $request->boolean('enabled');

        DB::transaction(function () use ($enabled, $request, $user) {
            AppSetting::putBoolean(OrderNotifications::SMS_ENABLED_SETTING, $enabled);

            AdminAudit::create([
                'entity_type' => 'app_setting',
                'entity_id' => 0,
                'action' => $enabled ? 'sms_enabled' : 'sms_disabled',
                'details' => 'Outbound order SMS '.($enabled ? 'turned on' : 'turned off').' via admin settings',
                'actor_user_id' => $user->id,
                'actor_role' => $user->role,
                'ip_address' => $request->ip(),
                'request_id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['enabled' => $enabled]);
        }

        return back();
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
