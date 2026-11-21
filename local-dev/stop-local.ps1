$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$runtime = Join-Path $repo '.local-test'
$pidFile = Join-Path $runtime 'server.pid'
if (-not (Test-Path $pidFile)) { Write-Host 'No saved local server process.'; return }
$serverId = [int](Get-Content -LiteralPath $pidFile)
$process = Get-CimInstance Win32_Process -Filter "ProcessId=$serverId" -ErrorAction SilentlyContinue
$php = [IO.Path]::GetFullPath((Join-Path $runtime 'php/php.exe'))
$router = Join-Path $PSScriptRoot 'router.php'
if ($process) {
    if ($process.ExecutablePath -ne $php -or -not $process.CommandLine -or -not $process.CommandLine.Contains($router)) {
        throw 'Saved PID belongs to a different process. Nothing was stopped.'
    }
    Stop-Process -Id $serverId -ErrorAction Stop
}
Remove-Item -LiteralPath $pidFile
Write-Host 'Local server stopped. Database and content retained.'
