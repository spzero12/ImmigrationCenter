# Build a clean FTP package into _deploy_build\
# Run from project root: powershell -ExecutionPolicy Bypass -File scripts\build_deploy.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out  = Join-Path $root '_deploy_build'

if (Test-Path $out) { Remove-Item $out -Recurse -Force }
New-Item -ItemType Directory $out | Out-Null

# 1. Root PHP pages (except legacy root config.php)
Get-ChildItem $root -Filter *.php -File | Where-Object { $_.Name -ne 'config.php' } |
    Copy-Item -Destination $out

# 2. Whole directories
foreach ($d in 'api','css','js','includes','config','vendor') {
    Copy-Item (Join-Path $root $d) (Join-Path $out $d) -Recurse
}

# 3. Working document templates only (no samples / build scripts)
New-Item -ItemType Directory (Join-Path $out 'templates') | Out-Null
Copy-Item (Join-Path $root 'templates\processes') (Join-Path $out 'templates\processes') -Recurse

# 4. Empty upload dirs + deny PHP execution inside them
foreach ($u in 'documents','employees','organizations') {
    New-Item -ItemType Directory (Join-Path $out "uploads\$u") -Force | Out-Null
}
@'
<FilesMatch "\.(php|phtml|php\d)$">
    Require all denied
</FilesMatch>
'@ | Set-Content (Join-Path $out 'uploads\.htaccess') -Encoding ascii

# 5. Deny direct web access to service dirs
foreach ($d in 'includes','config','templates') {
    'Require all denied' | Set-Content (Join-Path $out "$d\.htaccess") -Encoding ascii
}

Write-Host "Done: $out"
Write-Host ("Files: " + (Get-ChildItem $out -Recurse -File).Count)
