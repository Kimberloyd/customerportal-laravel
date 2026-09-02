param(
  [switch]$SkipBuild,
  [ValidateRange(30, 3600)]
  [int]$TimeoutSeconds = 600
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$composeFile = "docker-compose.local.yml"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
  Write-Error "Docker CLI was not found. Install Docker Desktop and re-run this script."
}

function Test-DockerReady {
  docker info *> $null
  return $LASTEXITCODE -eq 0
}

if (-not (Test-DockerReady)) {
  Write-Host "Docker daemon not responding - starting Docker Desktop..."
  $dockerDesktop = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
  if (Test-Path $dockerDesktop) {
    Start-Process -FilePath $dockerDesktop -WindowStyle Hidden
  } else {
    Write-Error "Docker Desktop was not found at $dockerDesktop. Start Docker manually and re-run this script."
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

if (-not (Test-Path -LiteralPath $composeFile)) {
  Write-Error "Local Compose file not found: $composeFile"
}

# Port 5173 can fall inside a Windows/Hyper-V excluded port range, which
# prevents Docker Desktop from publishing it even when no process owns it.
# Keep the host port overridable while using a safer local default.
if ([string]::IsNullOrWhiteSpace($env:VITE_HOST_PORT)) {
  $env:VITE_HOST_PORT = "5273"
}

docker compose -f $composeFile config --quiet
if ($LASTEXITCODE -ne 0) {
  Write-Error "The local Docker Compose configuration is invalid."
}

$services = @(docker compose -f $composeFile config --services)
if ($LASTEXITCODE -ne 0 -or $services.Count -eq 0) {
  Write-Error "No services were found in $composeFile."
}

$upArguments = @("compose", "-f", $composeFile, "up", "-d", "--remove-orphans")
if (-not $SkipBuild) {
  $upArguments += "--build"
  Write-Host "Building changed images and starting local services..."
} else {
  Write-Host "Starting local services without rebuilding images..."
}

& docker @upArguments
if ($LASTEXITCODE -ne 0) {
  Write-Error "Docker Compose startup failed. Review the output above."
}

# `up -d` only confirms that containers launched. Wait for every service
# currently declared in the local Compose file so newly added services are
# covered automatically. Services without a healthcheck are ready once they
# remain running; healthchecked services must report healthy.
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

$waited = 0
$lastReported = @{}

Write-Host ""
Write-Host "Waiting for containers to become healthy (a cold start may install Composer and npm dependencies)..."

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

  if ($waited -ge $TimeoutSeconds) {
    Write-Host ""
    Write-Host "Still not ready after $TimeoutSeconds seconds. Recent logs:"
    foreach ($service in $services) {
      if ($statuses[$service] -ne "healthy" -and $statuses[$service] -ne "n/a") {
        Write-Host "--- $service ---"
        docker compose -f $composeFile logs $service --tail 30
      }
    }
    Write-Error "Timed out waiting for containers to become healthy. Check the logs above, or run: docker compose -f $composeFile logs -f"
  }

  Start-Sleep -Seconds 3
  $waited += 3
}

Write-Host ""
Write-Host "Customer Portal is running:"
Write-Host "  App:  http://localhost:8180"
Write-Host "  Reverb WebSocket: ws://localhost:8181"
Write-Host "  Vite: http://localhost:$env:VITE_HOST_PORT"
Write-Host ""
Write-Host "Logs:  docker compose -f $composeFile logs -f"
Write-Host "Stop:  npm.cmd run stop"
Write-Host "Fast restart without rebuilding: npm.cmd run start -- -SkipBuild"
