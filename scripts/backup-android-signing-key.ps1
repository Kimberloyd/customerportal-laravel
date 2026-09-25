[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $DestinationDirectory,

    [string] $SourceKey = (Join-Path $env:USERPROFILE '.android\debug.keystore')
)

$ErrorActionPreference = 'Stop'

$source = (Resolve-Path -LiteralPath $SourceKey).Path
$repository = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path

New-Item -ItemType Directory -Path $DestinationDirectory -Force | Out-Null
$destination = (Resolve-Path -LiteralPath $DestinationDirectory).Path

if ($destination.StartsWith($repository, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Refusing to place a signing key inside the repository. Choose an encrypted external drive or password-manager attachment directory.'
}

$backup = Join-Path $destination 'customer-portal-current-update-key.keystore'
$checksumFile = "$backup.sha256"

if (Test-Path -LiteralPath $backup) {
    throw "Backup already exists: $backup. Verify or move it instead of overwriting it."
}

Copy-Item -LiteralPath $source -Destination $backup

$sourceHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $source).Hash.ToLowerInvariant()
$backupHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $backup).Hash.ToLowerInvariant()

if ($sourceHash -ne $backupHash) {
    Remove-Item -LiteralPath $backup -Force
    throw 'Backup verification failed: source and destination SHA-256 values differ.'
}

Set-Content -LiteralPath $checksumFile -Encoding ascii -NoNewline -Value "$backupHash  customer-portal-current-update-key.keystore`n"

Write-Host 'Signing-key backup created and verified.'
Write-Host "Backup:  $backup"
Write-Host "SHA-256: $backupHash"
Write-Warning 'This key currently controls updates for installed app copies. Keep at least two encrypted, access-controlled copies in separate locations.'
