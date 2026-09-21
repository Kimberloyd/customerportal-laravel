<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
        return response()->json([
            'latest_version_code' => config('mobile-app.latest_version_code'),
            'latest_version_name' => config('mobile-app.latest_version_name'),
            'min_version_code' => config('mobile-app.min_version_code'),
            // Withheld until the APK is actually on the server, so the app
            // never offers a download that would 404.
            'download_url' => $this->apkExists() ? route('mobile-app.download') : null,
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
}
