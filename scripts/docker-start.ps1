$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot

function Test-DockerReady {
  docker info *> $null
  return $LASTEXITCODE -eq 0
}

if (-not (Test-DockerReady)) {
  Write-Host "Docker daemon not responding - starting Docker Desktop..."
  $dockerDesktop = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
  if (Test-Path $dockerDesktop) {
    Start-Process $dockerDesktop
  }

  $waited = 0
  while (-not (Test-DockerReady)) {
    if ($waited -ge 120) {
      Write-Error "Docker Desktop did not become ready within 120 seconds. Start it manually and re-run this script."
    }
    Start-Sleep -Seconds 3
    $waited += 3
  }
  Write-Host "Docker daemon is up."
}

Set-Location -LiteralPath $root
docker compose -f docker-compose.local.yml up -d

Write-Host ""
Write-Host "Customer Portal is running:"
Write-Host "  App:  http://localhost:8090"
Write-Host "  Vite: http://localhost:5173"
Write-Host ""
Write-Host "Logs:  docker compose -f docker-compose.local.yml logs -f"
Write-Host "Stop:  npm.cmd run stop"
