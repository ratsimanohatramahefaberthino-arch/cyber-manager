# Installe l'écoute de découverte (UDP) en tâche planifiée : démarre avec Windows, relancée si elle plante.
# Le port HTTP du serveur est détecté automatiquement (ne rien saisir). -Port force un port (cas particulier).
# Normalement appelé par preparer-serveur.ps1. Usage direct (administrateur) :
#   powershell -ExecutionPolicy Bypass -File scripts\installer-decouverte.ps1 [-XamppDir E:\xampp] [-Port 8000] [-PortUdp 47474]
param(
    [int]$Port = 0,
    [string]$XamppDir = "C:\xampp",
    [int]$PortUdp = 47474
)
$ErrorActionPreference = "Stop"

$admin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $admin) { throw "Lancez PowerShell en tant qu'administrateur." }

$app = Split-Path -Parent $PSScriptRoot
$php = Join-Path $XamppDir "php\php.exe"
if (-not (Test-Path $php)) {
    $trouve = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($trouve) { $php = $trouve.Source }
}
$artisan = Join-Path $app "artisan"
if (-not (Test-Path $php)) { throw "php.exe introuvable (utilisez -XamppDir)" }
if (-not (Test-Path $artisan)) { throw "artisan introuvable : $artisan" }

# 1. Pare-feu : un seul port UDP, tous les types de réseau
$nomRegle = "Cyber Manager découverte (UDP $PortUdp)"
Get-NetFirewallRule -DisplayName $nomRegle -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName $nomRegle -Direction Inbound -Protocol UDP -LocalPort $PortUdp -Action Allow -Profile Any | Out-Null
Write-Host "Pare-feu : port UDP $PortUdp ouvert (tous réseaux)." -ForegroundColor Green

# 2. Tâche planifiée SYSTEM : au démarrage, sans limite de durée, relance automatique
$arguments = "`"$artisan`" cyber:decouverte --udp-port=$PortUdp"
if ($Port -gt 0) { $arguments += " --http-port=$Port" }

$nomTache = "CyberManager Decouverte"
$action = New-ScheduledTaskAction -Execute $php -Argument $arguments -WorkingDirectory $app
$declencheur = New-ScheduledTaskTrigger -AtStartup
$parametres = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew

Stop-ScheduledTask -TaskName $nomTache -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $nomTache -Action $action -Trigger $declencheur -Settings $parametres -User "SYSTEM" -RunLevel Highest -Force | Out-Null
Start-ScheduledTask -TaskName $nomTache
Start-Sleep -Seconds 3

$portTexte = if ($Port -gt 0) { "HTTP $Port" } else { "port HTTP détecté automatiquement" }
if ((Get-ScheduledTask -TaskName $nomTache).State -eq "Running") {
    Write-Host "Découverte : en écoute (UDP $PortUdp, $portTexte), démarrage automatique avec Windows." -ForegroundColor Green
} else {
    Write-Host "La tâche ne tourne pas. Testez à la main : $php `"$artisan`" cyber:decouverte" -ForegroundColor Yellow
}