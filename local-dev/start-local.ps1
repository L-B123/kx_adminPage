$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$repo = Split-Path $PSScriptRoot -Parent
$runtime = Join-Path $repo '.local-test'
$php = [IO.Path]::GetFullPath((Join-Path $runtime 'php/php.exe'))
$settingsFile = Join-Path $runtime 'site.json'
if (-not (Test-Path $php) -or -not (Test-Path $settingsFile) -or -not (Test-Path (Join-Path $runtime 'LOCAL-LOGIN.txt'))) {
    throw 'Run local-dev/setup-local.ps1 first.'
}
$settings = Get-Content -Raw -LiteralPath $settingsFile | ConvertFrom-Json
$router = Join-Path $PSScriptRoot 'router.php'
$pidFile = Join-Path $runtime 'server.pid'
if (Test-Path $pidFile) {
    $serverId = [int](Get-Content -LiteralPath $pidFile)
    $running = Get-CimInstance Win32_Process -Filter "ProcessId=$serverId" -ErrorAction SilentlyContinue
    if ($running -and $running.ExecutablePath -eq $php -and $running.CommandLine -and $running.CommandLine.Contains($router)) {
        Write-Host "Already running: $($settings.url)/"
        return
    }
    Remove-Item -LiteralPath $pidFile
}
$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, [int]$settings.port)
try { $listener.Start() } catch { throw "Port $($settings.port) is already in use. No process was stopped." } finally { $listener.Stop() }
# Resolve extension_dir="ext" from the PHP directory, just like setup-local.ps1.
$arguments = @('-c', 'php.ini', '-S', "127.0.0.1:$($settings.port)", '-t', ('"' + (Join-Path $runtime 'wordpress') + '"'), ('"' + $router + '"'))
$process = Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory (Split-Path $php -Parent) -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'server-output.log') -RedirectStandardError (Join-Path $runtime 'server-error.log') -PassThru
[IO.File]::WriteAllText($pidFile, [string]$process.Id)
$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    $process.Refresh()
    if ($process.HasExited) { throw 'PHP exited. See .local-test/server-error.log.' }
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri "$($settings.url)/wp-login.php" -TimeoutSec 3
        if ($response.StatusCode -eq 200 -and $response.Content.Contains('user_login')) { $ready = $true; break }
    } catch { Start-Sleep -Milliseconds 300 }
}
if (-not $ready) { throw 'The site did not pass its login-page health check. See .local-test/server-error.log.' }
foreach ($check in @(
    @{path='/';marker='competitions-list'},
    @{path='/competitions-list/';marker='dssast-competitions'},
    @{path='/notes-list/';marker='dssast-content-list'},
    @{path='/news-list/';marker='dssast-content-list'},
    @{path='/announcements-list/';marker='dssast-content-list'},
    @{path='/note-submit/';marker='wp-login.php'}
)) {
    $response = Invoke-WebRequest -UseBasicParsing -Uri ($settings.url + $check.path) -TimeoutSec 15
    if ($response.StatusCode -ne 200 -or -not $response.Content.Contains($check.marker)) {
        throw "Page health check failed: $($check.path). Server logs: .local-test/server-error.log"
    }
}
Write-Host "Site:  $($settings.url)/"
Write-Host "Admin: $($settings.url)/wp-admin/"
Write-Host "Credentials: $(Join-Path $runtime 'LOCAL-LOGIN.txt')"
