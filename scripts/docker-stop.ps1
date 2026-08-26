param(
  [switch]$KeepContainers
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$composeFile = "docker-compose.local.yml"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
  Write-Error "Docker CLI was not found. Install Docker Desktop and re-run this script."
}

Set-Location -LiteralPath $root

if (-not (Test-Path -LiteralPath $composeFile)) {
  Write-Error "Local Compose file not found: $composeFile"
}

docker info *> $null
if ($LASTEXITCODE -ne 0) {
  Write-Host "Docker is not running; Customer Portal is already stopped."
  exit 0
}

if ($KeepContainers) {
  docker compose -f $composeFile stop
  $successMessage = "Stopped Customer Portal containers without removing them."
} else {
  docker compose -f $composeFile down --remove-orphans
  $successMessage = "Stopped and removed Customer Portal containers and the local network."
}

if ($LASTEXITCODE -ne 0) {
  Write-Error "Docker Compose shutdown failed. Review the output above."
}

Write-Host $successMessage
Write-Host "Named volumes were preserved (database, Redis, Composer vendor, and Node modules)."
