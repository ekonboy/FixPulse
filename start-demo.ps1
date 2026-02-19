param(
    [switch]$NoClean,
    [int]$AstroPort = 4321
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$webDir = Join-Path $root 'apps\web'
$runnerDir = Join-Path $root 'apps\runner'
$astroDir = Join-Path $root 'web-astro'

if (-not (Test-Path $astroDir)) {
    throw "Astro demo folder not found: $astroDir"
}

function Get-MatchingProcessIds {
    param(
        [string[]]$Patterns
    )

    $all = Get-CimInstance Win32_Process | Where-Object {
        $_.Name -in @('php.exe', 'node.exe', 'powershell.exe', 'pwsh.exe')
    }

    $ids = @()
    foreach ($proc in $all) {
        $cmd = [string]$proc.CommandLine
        foreach ($pattern in $Patterns) {
            if ($cmd -match $pattern) {
                $ids += [int]$proc.ProcessId
                break
            }
        }
    }

    return $ids | Sort-Object -Unique
}

function Stop-DemoProcesses {
    $patterns = @(
        'artisan serve',
        'artisan queue:work',
        'src/server\.js',
        'npm(\.cmd)?\s+run\s+start',
        'astro(\.cmd)?\s+dev',
        [Regex]::Escape($webDir),
        [Regex]::Escape($runnerDir),
        [Regex]::Escape($astroDir)
    )

    $ids = Get-MatchingProcessIds -Patterns $patterns
    foreach ($id in $ids) {
        try {
            Stop-Process -Id $id -Force -ErrorAction Stop
            Write-Host "Stopped process PID $id"
        } catch {
            Write-Warning "Could not stop PID ${id}: $($_.Exception.Message)"
        }
    }
}

if (-not $NoClean) {
    Write-Host 'Cleaning previous demo processes...'
    Stop-DemoProcesses
    Start-Sleep -Seconds 1
}

$windowsShell = if (Get-Command pwsh -ErrorAction SilentlyContinue) { 'pwsh' } else { 'powershell' }

$webCmd = "Set-Location '$webDir'; php artisan serve --host=127.0.0.1 --port=8000"
$workerCmd = "Set-Location '$webDir'; php artisan queue:work --queue=scans,default --tries=2 --timeout=120"
$runnerCmd = "Set-Location '$runnerDir'; npm run start"
$astroCmd = "Set-Location '$astroDir'; npm run dev -- --host 0.0.0.0 --port $AstroPort"

Write-Host 'Starting Laravel web server...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $webCmd) | Out-Null

Write-Host 'Starting queue worker...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $workerCmd) | Out-Null

Write-Host 'Starting FixPulse runner...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $runnerCmd) | Out-Null

Write-Host 'Starting Astro demo...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $astroCmd) | Out-Null

Start-Sleep -Seconds 2

Write-Host ''
Write-Host 'Demo environment started.'
Write-Host 'FixPulse:      http://127.0.0.1:8000'
Write-Host 'Runner health: http://127.0.0.1:3333/health'
Write-Host "Astro demo:    http://localhost:$AstroPort"
Write-Host ''
Write-Host 'Use .\stop-dev.ps1 to stop FixPulse processes.'
Write-Host 'Close Astro terminal or run .\start-demo.ps1 again to clean and restart all.'
