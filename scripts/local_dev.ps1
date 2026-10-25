# Локальный стенд: MariaDB (порт 3307) + PHP dev server (порт 8001).
# Идемпотентен: данные БД создаются ОДИН раз и лежат вне репозитория
# (%USERPROFILE%\portal-localdb); повторные запуски только поднимают
# процессы. Пересоздать БД с нуля: scripts\local_dev.ps1 -Reset
# Вход: hr_demo / Demo12345 (демо-данные), admin / admin123.
param([switch]$Reset, [switch]$DbOnly)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$bin = 'C:\Program Files\MariaDB 12.3\bin'
$dataDir = Join-Path $env:USERPROFILE 'portal-localdb'
$port = 3307
$dbName = 'portal'

function Test-Port($p) {
    return [bool](Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue)
}

function Invoke-Sql($sql, $database) {
    $args = @('-uroot', "-P$port", '-h127.0.0.1', '--skip-ssl-verify-server-cert')
    if ($database) { $args += $database }
    & "$bin\mariadb.exe" @args -e $sql
}

if ($Reset -and (Test-Port $port)) {
    Invoke-SQL "DROP DATABASE IF EXISTS $dbName" $null
}

if (-not (Test-Path (Join-Path $dataDir 'my.ini'))) {
    & "$bin\mariadb-install-db.exe" "--datadir=$dataDir" '--password=' | Out-Null
}

if (-not (Test-Port $port)) {
    Start-Process -WindowStyle Hidden -FilePath "$bin\mariadbd.exe" -ArgumentList @(
        "--defaults-file=$dataDir\my.ini", "--port=$port", '--bind-address=127.0.0.1'
    ) -RedirectStandardError (Join-Path $dataDir 'run.log')

    for ($i = 0; $i -lt 30 -and -not (Test-Port $port); $i++) { Start-Sleep -Seconds 1 }
}

Invoke-Sql "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4" $null

$tables = Invoke-Sql "SHOW TABLES LIKE 'employees'" $dbName
if (-not $tables) {
    Write-Host 'Первичное наполнение локальной БД...'
    Get-Content -Raw -Encoding UTF8 (Join-Path $root 'db\production_rebuild.sql') |
        & "$bin\mariadb.exe" -uroot "-P$port" -h127.0.0.1 --skip-ssl-verify-server-cert $dbName
    & php -d "auto_prepend_file=$root\scripts\local\env.php" (Join-Path $root 'db\demo_seed.php')
}

if ($DbOnly) { return }

Set-Location $root
$webPort = if ($env:PORT) { $env:PORT } else { 8001 }
& php -d "auto_prepend_file=$root\scripts\local\env.php" -S "127.0.0.1:$webPort" -t $root
