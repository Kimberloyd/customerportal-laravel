<?php

namespace App\Http\Controllers;

use App\Models\AdminAudit;
use App\Models\AppSetting;
use App\Support\AdminUserListing;
use App\Support\OrderNotifications;
use App\Support\SemaphoreSms;
use App\Support\UserAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    /** Rows of message history per page in the Semaphore panel. */
    private const SMS_PAGE_SIZE = 6;

    public function edit(Request $request): Response
    {
        $user = Auth::user();
        $page = max(1, (int) $request->query('sms_page', 1));

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
            // Round trips to Semaphore, so they stay off the critical path --
            // the settings form renders immediately and the panel fills in.
            // Each is independently nullable: one endpoint being down still
            // shows the other.
            'semaphore' => $user->role === 'admin'
                ? Inertia::defer(function () use ($page) {
                    // Semaphore does not provide a total. Fetch one extra row
                    // so the shared numbered pagination only exposes a next
                    // page when there is actually another message to show.
                    $messages = SemaphoreSms::messages(self::SMS_PAGE_SIZE + 1, $page);
                    $hasMore = is_array($messages) && count($messages) > self::SMS_PAGE_SIZE;

                    return [
                        'account' => SemaphoreSms::account(),
                        'messages' => is_array($messages)
                            ? array_slice($messages, 0, self::SMS_PAGE_SIZE)
                            : null,
                        'page' => $page,
                        'has_more' => $hasMore,
                    ];
                }, 'semaphore')
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
