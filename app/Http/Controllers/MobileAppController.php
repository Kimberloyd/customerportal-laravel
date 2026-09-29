<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public on purpose: the download opens in the phone's system browser, which
 * has no portal session, and the APK is only a shell around this website.
 */
class MobileAppController extends Controller
{
    public function version(): JsonResponse
    {
        // Hashes the ~25MB file, so computed once per request and reused,
        // not once for the availability check and again for the payload.
        $checksum = $this->validChecksum();

        return response()->json([
            'latest_version_code' => config('mobile-app.latest_version_code'),
            'latest_version_name' => config('mobile-app.latest_version_name'),
            'min_version_code' => config('mobile-app.min_version_code'),
            // Withheld until the APK is actually on the server, so the app
            // never offers a download that would 404.
            'download_url' => $checksum !== null ? route('mobile-app.download') : null,
            'apk_sha256' => $checksum,
        ]);
    }

    public function download(): BinaryFileResponse
    {
        abort_unless($this->apkExists(), 404);

        return response()->download(
            Storage::disk('local')->path(config('mobile-app.apk_path')),
            'customer-portal.apk',
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    private function apkExists(): bool
    {
        return Storage::disk('local')->exists(config('mobile-app.apk_path'));
    }

    /**
     * The app refuses to install anything whose downloaded bytes don't hash
     * to this value (see AppUpdatePlugin.java), so it must be the real hash
     * of the file actually on disk -- not just whatever's typed into
     * MOBILE_APP_APK_SHA256. A release that swaps the APK without updating
     * that setting (or updates the setting to a value that was never
     * uploaded) would otherwise advertise an update every install of which
     * fails the same way, with nothing pointing at why. Verifying it here
     * means an operator finds out from the log the moment it happens
     * instead of from a confused customer.
     */
    private function validChecksum(): ?string
    {
        $configured = strtolower((string) config('mobile-app.apk_sha256'));
        if (preg_match('/\A[0-9a-f]{64}\z/', $configured) !== 1) {
            return null;
        }

        if (! $this->apkExists()) {
            return null;
        }

        $actual = hash_file('sha256', Storage::disk('local')->path(config('mobile-app.apk_path')));

        if ($actual !== $configured) {
            Log::warning('Mobile app update withheld: MOBILE_APP_APK_SHA256 does not match the uploaded APK.', [
                'configured' => $configured,
                'actual' => $actual,
            ]);

            return null;
        }

        return $configured;
    }
}
