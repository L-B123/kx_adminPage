param([ValidateRange(1024,65535)][int]$Port = 8097)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$ProgressPreference = 'SilentlyContinue'
$repo = Split-Path $PSScriptRoot -Parent
$runtime = Join-Path $repo '.local-test'
$phpDir = Join-Path $runtime 'php'
$php = Join-Path $phpDir 'php.exe'
$site = Join-Path $runtime 'wordpress'
$cache = Join-Path $runtime 'downloads'
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Download-File([string]$Url, [string]$Destination) {
    Write-Host "Downloading $Url"
    $partial = "$Destination.partial"
    Invoke-WebRequest -UseBasicParsing -Uri $Url -OutFile $partial -TimeoutSec 300
    Move-Item -LiteralPath $partial -Destination $Destination -Force
}

if ($env:OS -ne 'Windows_NT' -or -not [Environment]::Is64BitOperatingSystem) {
    throw 'Run this script in 64-bit Windows PowerShell, not in WSL.'
}
New-Item -ItemType Directory -Force -Path $runtime,$cache,$phpDir,(Join-Path $runtime 'database') | Out-Null
$settings = Join-Path $runtime 'site.json'
if (Test-Path $settings) {
    $existing = Get-Content -Raw -LiteralPath $settings | ConvertFrom-Json
    if ([int]$existing.port -ne $Port) {
        throw "This site already uses port $($existing.port). Rerun with -Port $($existing.port)."
    }
} else {
    $probe = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $Port)
    try { $probe.Start() } catch { throw "Port $Port is already in use. Rerun setup with -Port 8098." } finally { $probe.Stop() }
    [IO.File]::WriteAllText($settings, (@{port=$Port;url="http://127.0.0.1:$Port"} | ConvertTo-Json), $utf8)
}

if (-not (Test-Path $php)) {
    $releases = Invoke-RestMethod -Uri 'https://downloads.php.net/~windows/releases/releases.json' -TimeoutSec 60
    $package = $releases.'8.3'.'nts-vs16-x64'.zip
    $archive = Join-Path $cache $package.path
    if (-not (Test-Path $archive)) {
        Download-File "https://downloads.php.net/~windows/releases/$($package.path)" $archive
    }
    if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash -ne $package.sha256) {
        Remove-Item -LiteralPath $archive
        throw 'PHP archive SHA256 mismatch. Rerun to download again.'
    }
    Expand-Archive -LiteralPath $archive -DestinationPath $phpDir -Force
}

$ini = @'
[PHP]
extension_dir="ext"
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
date.timezone=Asia/Shanghai
memory_limit=256M
upload_max_filesize=8M
post_max_size=10M
max_execution_time=120
display_errors=Off
log_errors=On
'@
# Keep php.ini ASCII-only: PHP's Windows DLL loader can misread UTF-8 paths.
# Both CLI commands and the server run from phpDir, so "ext" is unambiguous.
[IO.File]::WriteAllText((Join-Path $phpDir 'php.ini'), $ini, $utf8)
Push-Location -LiteralPath $phpDir
try {
    & $php -c 'php.ini' (Join-Path $PSScriptRoot 'check-environment.php')
    if ($LASTEXITCODE -ne 0) {
        throw 'PHP environment check failed. See the extension error above. If PHP itself cannot start because VCRUNTIME is missing, install https://aka.ms/vs/17/release/vc_redist.x64.exe'
    }
} finally {
    Pop-Location
}

if (-not (Test-Path (Join-Path $site 'wp-load.php'))) {
    $archive = Join-Path $cache 'wordpress.zip'
    Download-File 'https://wordpress.org/latest.zip' $archive
    Expand-Archive -LiteralPath $archive -DestinationPath $runtime -Force
}
$sqliteDir = Join-Path $site 'wp-content/plugins/sqlite-database-integration'
if (-not (Test-Path (Join-Path $sqliteDir 'db.copy'))) {
    $archive = Join-Path $cache 'sqlite-database-integration.zip'
    Download-File 'https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip' $archive
    Expand-Archive -LiteralPath $archive -DestinationPath (Join-Path $site 'wp-content/plugins') -Force
}
$dropin = Join-Path $site 'wp-content/db.php'
if (-not (Test-Path $dropin)) {
    Copy-Item -LiteralPath (Join-Path $sqliteDir 'db.copy') -Destination $dropin
}

# Copy only this plugin; leave the database, uploads and WordPress installation intact.
$pluginDir = Join-Path $site 'wp-content/plugins/dssast-cms'
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null
Copy-Item -Path (Join-Path $repo 'dssast-cms/*') -Destination $pluginDir -Recurse -Force
Push-Location -LiteralPath $phpDir
try {
    & $php -c 'php.ini' (Join-Path $PSScriptRoot 'bootstrap.php') $runtime
    if ($LASTEXITCODE -ne 0) { throw 'WordPress initialization failed. Existing data has been retained.' }
} finally {
    Pop-Location
}
& (Join-Path $PSScriptRoot 'start-local.ps1')
