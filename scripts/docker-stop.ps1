$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot

Set-Location -LiteralPath $root
docker compose -f docker-compose.local.yml stop

Write-Host "Stopped Customer Portal containers."
