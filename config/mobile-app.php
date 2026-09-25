<?php

return [
    /*
     * The Android app (mobile/) is a thin Capacitor shell around this site, so
     * only native changes need a new APK. To announce one: bump versionCode in
     * mobile/android/app/build.gradle, build the APK, copy it to
     * storage/app/private/<apk_path> on the server, then raise
     * latest_version_code to that versionCode. Raise min_version_code as well
     * to make the update mandatory (the in-app notice can't be dismissed).
     *
     * The default of 1 means no notice is shown until these are set.
     */
    'latest_version_code' => (int) env('MOBILE_APP_LATEST_VERSION_CODE', 1),
    'latest_version_name' => env('MOBILE_APP_LATEST_VERSION_NAME', '1.0'),
    'min_version_code' => (int) env('MOBILE_APP_MIN_VERSION_CODE', 1),

    // Relative to the "local" disk root (storage/app/private, a persistent volume).
    'apk_path' => env('MOBILE_APP_APK_PATH', 'mobile/customer-portal.apk'),
    'apk_sha256' => env('MOBILE_APP_APK_SHA256'),
];
