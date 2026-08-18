$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$composeFile = "docker-compose.local.yml"

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
docker compose -f $composeFile up -d
if ($LASTEXITCODE -ne 0) {
  Write-Error "docker compose up failed -- see the output above."
}

# `up -d` only means the containers *launched*, not that the app is
# actually ready to serve. The app container's first boot (no image
# layer caching -- see the comment in the compose file) can take several
# minutes to install PHP extensions and Composer deps before
# `php artisan serve` even starts, so wait for the healthchecks in the
# compose file to actually go green before declaring success -- that's
# the difference between this script and a plain `docker compose up -d`.
function Get-ServiceHealth([string]$service) {
  $lines = docker compose -f $composeFile ps --format json 2>$null
  foreach ($line in $lines) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $entry = $line | ConvertFrom-Json
    if ($entry.Service -eq $service) {
      return @{ State = $entry.State; Health = $entry.Health }
    }
  }
  return $null
}

$services = @("db", "app", "vite")
$timeoutSeconds = 600
$waited = 0
$lastReported = @{}

Write-Host ""
Write-Host "Waiting for containers to become healthy (first run can take several minutes -- installing PHP extensions and Composer deps)..."

while ($true) {
  $statuses = @{}
  $allHealthy = $true
  $anyFailed = $false

  foreach ($service in $services) {
    $info = Get-ServiceHealth $service
    if ($null -eq $info) {
      $statuses[$service] = "missing"
      $allHealthy = $false
      continue
    }
    if ($info.State -ne "running") {
      $statuses[$service] = "exited"
      $allHealthy = $false
      $anyFailed = $true
      continue
    }
    $health = if ([string]::IsNullOrWhiteSpace($info.Health)) { "n/a" } else { $info.Health }
    $statuses[$service] = $health
    if ($health -eq "unhealthy") {
      $anyFailed = $true
    }
    if ($health -ne "healthy" -and $health -ne "n/a") {
      $allHealthy = $false
    }
  }

  foreach ($service in $services) {
    if ($lastReported[$service] -ne $statuses[$service]) {
      Write-Host "  $service -> $($statuses[$service])"
      $lastReported[$service] = $statuses[$service]
    }
  }

  if ($allHealthy) { break }

  if ($anyFailed) {
    Write-Host ""
    Write-Host "A container failed to start. Recent logs:"
    foreach ($service in $services) {
      if ($statuses[$service] -eq "exited" -or $statuses[$service] -eq "unhealthy") {
        Write-Host "--- $service ---"
        docker compose -f $composeFile logs $service --tail 30
      }
    }
    Write-Error "Startup failed -- see logs above."
  }

  if ($waited -ge $timeoutSeconds) {
    Write-Host ""
    Write-Host "Still not healthy after $timeoutSeconds seconds. Recent app logs:"
    docker compose -f $composeFile logs app --tail 30
    Write-Error "Timed out waiting for containers to become healthy. Check the logs above, or run: docker compose -f $composeFile logs -f"
  }

  Start-Sleep -Seconds 3
  $waited += 3
}

Write-Host ""
Write-Host "Customer Portal is running:"
Write-Host "  App:  http://localhost:8090"
Write-Host "  Vite: http://localhost:5173"
Write-Host ""
Write-Host "Logs:  docker compose -f $composeFile logs -f"
Write-Host "Stop:  npm.cmd run stop"
