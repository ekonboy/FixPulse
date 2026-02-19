param(
    [switch]$NoClean
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$webDir = Join-Path $root 'apps\web'
$runnerDir = Join-Path $root 'apps\runner'

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

function Stop-DevProcesses {
    $patterns = @(
        'artisan serve',
        'artisan queue:work',
        'src/server\.js',
        'npm(\.cmd)?\s+run\s+start',
        [Regex]::Escape($webDir),
        [Regex]::Escape($runnerDir)
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
    Write-Host 'Cleaning previous FixPulse dev processes...'
    Stop-DevProcesses
    Start-Sleep -Seconds 1
}

$windowsShell = if (Get-Command pwsh -ErrorAction SilentlyContinue) { 'pwsh' } else { 'powershell' }

$webCmd = "Set-Location '$webDir'; php artisan serve --host=127.0.0.1 --port=8000"
$workerCmd = "Set-Location '$webDir'; php artisan queue:work --queue=scans,default --tries=2 --timeout=120"
$runnerCmd = "Set-Location '$runnerDir'; npm run start"

Write-Host 'Starting Laravel web server...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $webCmd) | Out-Null

Write-Host 'Starting queue worker...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $workerCmd) | Out-Null

Write-Host 'Starting runner...'
Start-Process -FilePath $windowsShell -ArgumentList @('-NoExit', '-Command', $runnerCmd) | Out-Null

Start-Sleep -Seconds 2

Write-Host ''
Write-Host 'FixPulse dev environment started.'
Write-Host 'Web:    http://127.0.0.1:8000'
Write-Host 'Runner: http://127.0.0.1:3333/health'
Write-Host ''
Write-Host 'Use .\stop-dev.ps1 to stop everything.'
