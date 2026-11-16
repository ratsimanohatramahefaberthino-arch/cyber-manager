# Sauvegarde de la base MySQL (zip daté, 14 jours conservés).
# Usage : powershell -ExecutionPolicy Bypass -File scripts\sauvegarde-base.ps1 [-Destination D:\Sauvegardes] [-XamppDir C:\xampp] [-Jours 14]
param(
    [string]$XamppDir = "C:\xampp",
    [string]$Destination = "",
    [int]$Jours = 14
)
$ErrorActionPreference = "Stop"

$app = Split-Path -Parent $PSScriptRoot
if (-not $Destination) { $Destination = Join-Path $app "storage\app\sauvegardes" }
New-Item -ItemType Directory $Destination -Force | Out-Null
$log = Join-Path $Destination "sauvegarde.log"
function Ecrire($m) { Add-Content $log ("[{0}] {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $m) }

try {
    # Lecture du .env (jamais de mot de passe dans la ligne de commande ni dans le journal)
    $env = @{}
    Get-Content (Join-Path $app ".env") | ForEach-Object {
        if ($_ -match '^\s*([A-Z_]+)\s*=\s*"?([^"#]*)"?\s*$') { $env[$Matches[1]] = $Matches[2].Trim() }
    }
    $base = $env["DB_DATABASE"]; $user = $env["DB_USERNAME"]; $mdp = $env["DB_PASSWORD"]
    $hote = if ($env["DB_HOST"]) { $env["DB_HOST"] } else { "127.0.0.1" }
    $port = if ($env["DB_PORT"]) { $env["DB_PORT"] } else { "3306" }
    if (-not $base) { throw "DB_DATABASE introuvable dans .env" }

    $dump = Join-Path $XamppDir "mysql\bin\mysqldump.exe"
    if (-not (Test-Path $dump)) { throw "mysqldump introuvable : $dump (utilisez -XamppDir)" }

    $cnf = Join-Path $env:TEMP ("cm-" + [guid]::NewGuid().ToString("N") + ".cnf")
    "[client]`nuser=$user`npassword=$mdp`nhost=$hote`nport=$port" | Set-Content $cnf -Encoding ASCII

    $horodatage = Get-Date -Format "yyyy-MM-dd_HHmm"
    $sql = Join-Path $Destination "base-$horodatage.sql"
    try {
        & $dump "--defaults-extra-file=$cnf" --single-transaction --routines --default-character-set=utf8mb4 "--result-file=$sql" $base
        if ($LASTEXITCODE -ne 0) { throw "mysqldump a échoué (code $LASTEXITCODE)" }
    } finally { Remove-Item $cnf -Force -ErrorAction SilentlyContinue }

    Compress-Archive -Path $sql -DestinationPath "$sql.zip" -Force
    Remove-Item $sql -Force
    Ecrire "OK : base-$horodatage.sql.zip ($([math]::Round((Get-Item "$sql.zip").Length / 1KB)) Ko)"

    Get-ChildItem $Destination -Filter "base-*.sql.zip" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$Jours) } | Remove-Item -Force
} catch {
    Ecrire "ÉCHEC : $($_.Exception.Message)"
    exit 1
}