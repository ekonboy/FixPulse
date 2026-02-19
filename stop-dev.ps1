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

$patterns = @(
    'artisan serve',
    'artisan queue:work',
    'src/server\.js',
    'npm(\.cmd)?\s+run\s+start',
    [Regex]::Escape($webDir),
    [Regex]::Escape($runnerDir)
)

$ids = Get-MatchingProcessIds -Patterns $patterns
if ((@($ids)).Count -eq 0) {
    Write-Host 'No FixPulse dev processes found.'
    exit 0
}

foreach ($id in $ids) {
    try {
        Stop-Process -Id $id -Force -ErrorAction Stop
        Write-Host "Stopped process PID $id"
    } catch {
        Write-Warning "Could not stop PID ${id}: $($_.Exception.Message)"
    }
}

Write-Host 'FixPulse dev processes stopped.'
