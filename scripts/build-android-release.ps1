[CmdletBinding()]
param(
    [string] $KeyStore = (Join-Path $env:USERPROFILE 'Downloads\CustomerPortal-Key-Backup\customer-portal-current-update-key.keystore'),
    [string] $KeyAlias = 'androiddebugkey',
    [string] $ExpectedCertificateSha256 = '552cc4d96a02eb423607d51ea6bacc0ab214dc3938ee43c748c97ae0f800ce87',
    [string] $OutputDirectory = (Join-Path $PSScriptRoot '..\mobile\releases')
)

$ErrorActionPreference = 'Stop'
$repository = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$mobile = Join-Path $repository 'mobile'
$android = Join-Path $mobile 'android'
$buildFile = Join-Path $android 'app\build.gradle'
$sourceApk = Join-Path $android 'app\build\outputs\apk\release\app-release.apk'

if (-not (Test-Path -LiteralPath $KeyStore -PathType Leaf)) {
    throw "Signing key not found: $KeyStore"
}

$storePasswordSecure = Read-Host 'Keystore password' -AsSecureString
$keyPasswordSecure = Read-Host 'Key password' -AsSecureString
$storePassword = [System.Net.NetworkCredential]::new('', $storePasswordSecure).Password
$keyPassword = [System.Net.NetworkCredential]::new('', $keyPasswordSecure).Password

if ([string]::IsNullOrWhiteSpace($storePassword) -or [string]::IsNullOrWhiteSpace($keyPassword)) {
    throw 'Signing passwords cannot be empty.'
}

$buildText = Get-Content -Raw -LiteralPath $buildFile
$versionCodeMatch = [regex]::Match($buildText, '(?m)^\s*versionCode\s+(\d+)\s*$')
$versionNameMatch = [regex]::Match($buildText, '(?m)^\s*versionName\s+"([^"]+)"\s*$')
if (-not $versionCodeMatch.Success -or -not $versionNameMatch.Success) {
    throw 'Could not read versionCode/versionName from app/build.gradle.'
}

$versionCode = $versionCodeMatch.Groups[1].Value
$versionName = $versionNameMatch.Groups[1].Value

$jdkCandidates = @(
    $env:JAVA_HOME,
    'C:\Program Files\Android\Android Studio\jbr',
    'C:\Program Files\Android\Android Studio\jre'
) | Where-Object { $_ -and (Test-Path -LiteralPath (Join-Path $_ 'bin\java.exe')) }
$jdk = $jdkCandidates | Select-Object -First 1
if (-not $jdk) {
    throw 'A Java runtime was not found. Install Android Studio or set JAVA_HOME.'
}

$sdk = if ($env:ANDROID_HOME) { $env:ANDROID_HOME } else { $env:ANDROID_SDK_ROOT }
if (-not $sdk) {
    throw 'Set ANDROID_HOME or ANDROID_SDK_ROOT to the Android SDK.'
}

$apksigner = Get-ChildItem -LiteralPath (Join-Path $sdk 'build-tools') -Filter 'apksigner.bat' -Recurse |
    Sort-Object { [version]$_.Directory.Name } -Descending |
    Select-Object -First 1
if (-not $apksigner) {
    throw 'apksigner.bat was not found in the Android SDK build-tools directory.'
}

$previous = @{}
foreach ($name in @('JAVA_HOME', 'ANDROID_RELEASE_STORE_FILE', 'ANDROID_RELEASE_STORE_PASSWORD', 'ANDROID_RELEASE_KEY_ALIAS', 'ANDROID_RELEASE_KEY_PASSWORD')) {
    $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}

try {
    $env:JAVA_HOME = $jdk
    $env:ANDROID_RELEASE_STORE_FILE = (Resolve-Path -LiteralPath $KeyStore).Path
    $env:ANDROID_RELEASE_STORE_PASSWORD = $storePassword
    $env:ANDROID_RELEASE_KEY_ALIAS = $KeyAlias
    $env:ANDROID_RELEASE_KEY_PASSWORD = $keyPassword

    Push-Location $mobile
    try {
        & npm.cmd ci
        if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
        & npx.cmd cap sync android
        if ($LASTEXITCODE -ne 0) { throw 'Capacitor sync failed.' }
    } finally {
        Pop-Location
    }

    Push-Location $android
    try {
        & .\gradlew.bat :app:clean :app:assembleRelease
        if ($LASTEXITCODE -ne 0) { throw 'Android release build failed.' }
    } finally {
        Pop-Location
    }
} finally {
    $storePassword = $null
    $keyPassword = $null
    foreach ($name in $previous.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process')
    }
}

if (-not (Test-Path -LiteralPath $sourceApk -PathType Leaf)) {
    throw "Expected APK was not produced: $sourceApk"
}

$signerOutput = & $apksigner.FullName verify --verbose --print-certs $sourceApk 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "APK signature verification failed:`n$($signerOutput -join [Environment]::NewLine)"
}

$digestLine = $signerOutput | Where-Object { $_ -match 'certificate SHA-256 digest:\s*([0-9a-fA-F]+)' } | Select-Object -First 1
if (-not $digestLine) {
    throw 'apksigner did not report a certificate SHA-256 digest.'
}
$actualCertificateSha256 = ([regex]::Match($digestLine, 'digest:\s*([0-9a-fA-F]+)').Groups[1].Value).ToLowerInvariant()
if ($actualCertificateSha256 -ne $ExpectedCertificateSha256.ToLowerInvariant()) {
    throw "Refusing release: signer $actualCertificateSha256 does not match expected signer $ExpectedCertificateSha256."
}

New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
$artifact = Join-Path $OutputDirectory "customer-portal-$versionName.apk"
Copy-Item -LiteralPath $sourceApk -Destination $artifact -Force
$artifactHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $artifact).Hash.ToLowerInvariant()
$manifest = [ordered]@{
    version_code = [int]$versionCode
    version_name = $versionName
    artifact = [IO.Path]::GetFileName($artifact)
    sha256 = $artifactHash
    certificate_sha256 = $actualCertificateSha256
    built_at_utc = [DateTime]::UtcNow.ToString('o')
}
$manifest | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $OutputDirectory 'release-manifest.json') -Encoding utf8
Set-Content -LiteralPath "$artifact.sha256" -Encoding ascii -Value "$artifactHash  $([IO.Path]::GetFileName($artifact))"

Write-Host "Release APK verified: $artifact"
Write-Host "Version: $versionName ($versionCode)"
Write-Host "APK SHA-256: $artifactHash"
Write-Host "Signer SHA-256: $actualCertificateSha256"
Write-Host "Production setting: MOBILE_APP_APK_SHA256=$artifactHash"
