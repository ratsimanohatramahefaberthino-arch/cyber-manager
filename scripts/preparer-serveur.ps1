# Préparation du PC serveur (à lancer UNE fois, en administrateur) :
#   - ouvre UN seul port dans le pare-feu, pour tous les types de réseau (le pare-feu reste actif)
#   - installe Apache et MySQL comme services Windows, démarrage automatique (sauf avec -SansServices)
#   - planifie la sauvegarde quotidienne de la base
#   - installe l'écoute de découverte : les postes retrouvent seuls le serveur, même si son adresse ou son port change
# -SansServices : n'installe pas Apache/MySQL (PC où XAMPP est piloté depuis son panneau de contrôle, ou « artisan serve »).
# Usage : powershell -ExecutionPolicy Bypass -File scripts\preparer-serveur.ps1 -Port 8000 [-XamppDir C:\xampp] [-Destination D:\Sauvegardes] [-Heure 03:00] [-SansServices]
param(
    [Parameter(Mandatory = $true)][int]$Port,
    [string]$XamppDir = "C:\xampp",
    [string]$Destination = "",
    [string]$Heure = "03:00",
    [switch]$SansServices
)
$ErrorActionPreference = "Stop"

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { throw "Lancez PowerShell en tant qu'administrateur." }

# 1. Pare-feu : un seul port, profils Domaine + Privé + Public (un réseau de cyber est souvent « Public »)
$nomRegle = "Cyber Manager (port $Port)"
Get-NetFirewallRule -DisplayName $nomRegle -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName $nomRegle -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any | Out-Null
Write-Host "Pare-feu : port $Port ouvert (TCP, tous réseaux)." -ForegroundColor Green

# 2. Apache et MySQL en services Windows, démarrage automatique
function Preparer-Service($nom, $exe, $argsInstall) {
    if (-not (Test-Path $exe)) { Write-Host "Introuvable : $exe (vérifiez -XamppDir)" -ForegroundColor Yellow; return }
    if (-not (Get-Service $nom -ErrorAction SilentlyContinue)) { & $exe @argsInstall | Out-Null }
    if (Get-Service $nom -ErrorAction SilentlyContinue) {
        Set-Service $nom -StartupType Automatic
        if ((Get-Service $nom).Status -ne "Running") { Start-Service $nom -ErrorAction SilentlyContinue }
        Write-Host "Service $nom : démarrage automatique." -ForegroundColor Green
    }
}
if ($SansServices) {
    Write-Host "Services Apache/MySQL : ignorés (-SansServices)." -ForegroundColor Yellow
} else {
    Preparer-Service "Apache2.4" (Join-Path $XamppDir "apache\bin\httpd.exe") @("-k", "install")
    Preparer-Service "MySQL"     (Join-Path $XamppDir "mysql\bin\mysqld.exe") @("--install", "MySQL")
}

# 3. Sauvegarde quotidienne
$script = Join-Path $PSScriptRoot "sauvegarde-base.ps1"
$argsTache = "-NoProfile -ExecutionPolicy Bypass -File `"$script`" -XamppDir `"$XamppDir`""
if ($Destination) { $argsTache += " -Destination `"$Destination`"" }
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $argsTache
$declencheur = New-ScheduledTaskTrigger -Daily -At $Heure
$parametres = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries
Register-ScheduledTask -TaskName "CyberManager Sauvegarde" -Action $action -Trigger $declencheur -Settings $parametres -User "SYSTEM" -RunLevel Highest -Force | Out-Null
Write-Host "Sauvegarde : chaque jour à $Heure." -ForegroundColor Green

# 4. Découverte automatique : les postes retrouvent seuls le serveur (UDP 47474). Le port HTTP est détecté tout seul.
& (Join-Path $PSScriptRoot "installer-decouverte.ps1") -XamppDir $XamppDir

Write-Host "Terminé. Conseil : mettez -Destination sur un AUTRE disque ou une clé, pas sur le même disque que la base." -ForegroundColor Cyan