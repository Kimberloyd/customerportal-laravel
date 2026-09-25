# Android release signing

## Current compatibility constraint

Android only accepts an update when its signing identity is compatible with the installed app. Existing Customer Portal 1.3 installations were signed by the current Android debug keystore. Losing that key means those installations cannot receive another ordinary in-place update.

Do not commit a keystore, passwords, `keystore.properties`, or copied key material. The repository ignores those file types as a second line of defense.

## Immediate backup

Run this from PowerShell and choose a directory on an encrypted external drive or another protected location outside this repository:

```powershell
.\scripts\backup-android-signing-key.ps1 -DestinationDirectory 'E:\CustomerPortal-Key-Backup'
```

The script refuses repository destinations, refuses to overwrite an existing backup, and verifies the copy with SHA-256. Make a second protected copy in a separate location. Record who can access it and test the checksum periodically.

## Controlled migration

For the existing sideloaded application, keep using the current key while choosing one of these migration paths:

1. Continue the current application ID and protect the existing key as the long-term update key.
2. Publish through an app store and use that store's supported signing-key upgrade process, after verifying eligibility and device support.
3. Introduce a new application ID signed by a new release key. This requires a one-time separate installation and migration from the old app.

Do not simply replace the key for the existing application ID; installed copies will reject that APK.

## Release build environment

Release builds now require all four variables and fail closed when any is absent:

```powershell
$env:ANDROID_RELEASE_STORE_FILE = 'E:\secure\customer-portal-current-update-key.keystore'
$env:ANDROID_RELEASE_STORE_PASSWORD = '<secret>'
$env:ANDROID_RELEASE_KEY_ALIAS = '<alias>'
$env:ANDROID_RELEASE_KEY_PASSWORD = '<secret>'

Set-Location mobile\android
.\gradlew.bat assembleRelease
```

Store passwords in a password manager or CI secret store. Do not save them in shell history, tracked files, Gradle files, or screenshots.

Before distributing an APK, verify its signer matches the currently installed release, verify its version code/name, and retain its SHA-256 checksum with the release record.

The normal release command performs those checks and writes the APK, checksum, and a JSON release manifest under the ignored `mobile/releases` directory:

```powershell
.\scripts\build-android-release.ps1
```

It prompts for both passwords without echoing them. Its default expected certificate digest is the signer verified on the currently deployed 1.3 APK, preventing an incompatible key from being published accidentally.
