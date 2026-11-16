# Installe l'écoute de découverte (UDP) en tâche planifiée : démarre avec Windows, relancée si elle plante.
# Normalement appelé par preparer-serveur.ps1. Usage direct (administrateur) :
#   powershell -ExecutionPolicy Bypass -File scripts\installer-decouverte.ps1 -Port 8000 [-XamppDir E:\xampp] [-PortUdp 47474]
param(
    [Parameter(Mandatory = $true)][int]$Port,
    [string]$XamppDir = "C:\xampp",
    [int]$PortUdp = 47474
)
$ErrorActionPreference = "Stop"

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { throw "Lancez PowerShell en tant qu'administrateur." }

$app = Split-Path -Parent $PSScriptRoot
$php = Join-Path $XamppDir "php\php.exe"
$artisan = Join-Path $app "artisan"
if (-not (Test-Path $php)) { throw "php.exe introuvable : $php (utilisez -XamppDir)" }
if (-not (Test-Path $artisan)) { throw "artisan introuvable : $artisan" }

# 1. Pare-feu : un seul port UDP, tous les types de réseau
$nomRegle = "Cyber Manager découverte (UDP $PortUdp)"
Get-NetFirewallRule -DisplayName $nomRegle -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName $nomRegle -Direction Inbound -Protocol UDP -LocalPort $PortUdp -Action Allow -Profile Any | Out-Null
Write-Host "Pare-feu : port UDP $PortUdp ouvert (tous réseaux)." -ForegroundColor Green

# 2. Tâche planifiée SYSTEM : au démarrage, sans limite de durée, relance automatique
$nomTache = "CyberManager Decouverte"
$action = New-ScheduledTaskAction -Execute $php -Argument "`"$artisan`" cyber:decouverte --http-port=$Port --udp-port=$PortUdp" -WorkingDirectory $app
$declencheur = New-ScheduledTaskTrigger -AtStartup
$parametres = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew

Stop-ScheduledTask -TaskName $nomTache -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $nomTache -Action $action -Trigger $declencheur -Settings $parametres -User "SYSTEM" -RunLevel Highest -Force | Out-Null
Start-ScheduledTask -TaskName $nomTache
Start-Sleep -Seconds 3

if ((Get-ScheduledTask -TaskName $nomTache).State -eq "Running") {
    Write-Host "Découverte : en écoute (UDP $PortUdp, HTTP $Port), démarrage automatique avec Windows." -ForegroundColor Green
} else {
    Write-Host "La tâche ne tourne pas. Testez à la main : $php `"$artisan`" cyber:decouverte --http-port=$Port" -ForegroundColor Yellow
}