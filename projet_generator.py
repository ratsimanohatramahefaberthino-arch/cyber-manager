# -*- coding: utf-8 -*-
"""projet_generator.py — Cyber Manager : génère GENERATOR.txt"""

import os
import sys
from datetime import datetime
from pathlib import Path

RACINE = Path(__file__).resolve().parent
FICHIER_SORTIE = RACINE / "GENERATOR.txt"
TAILLE_MAX_KO = 500

SECTIONS = {

    # ═══════════════════════════════════════════════════════════════
    # 1. WI-FI — le cœur du travail actuel
    # ═══════════════════════════════════════════════════════════════
    "WIFI - Controller et vues": [
        "app/Http/Controllers/WifiController.php",
        "resources/views/wifi/index.blade.php",
        "resources/views/wifi/comptes.blade.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 2. WI-FI — Services et modèles liés
    # ═══════════════════════════════════════════════════════════════
    "WIFI - Services et modèles": [
        "app/Services/WifiSessionService.php",
        "app/Services/MikroTikService.php",
        "app/Services/SessionService.php",
        "app/Models/Session.php",
        "app/Models/AppareilWifi.php",
        "app/Models/Voucher.php",
        "app/Models/LotVoucher.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 3. COMMANDES ARTISAN (pour comprendre la sync)
    # ═══════════════════════════════════════════════════════════════
    "COMMANDES": [
        "app/Console/Commands/WifiSyncSessionsCommand.php",
        "app/Console/Commands/ExpireSessions.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 4. TARIFS (pour le calcul du montant dû Wi-Fi)
    # ═══════════════════════════════════════════════════════════════
    "TARIFS": [
        "app/Models/Tarif.php",
        "app/Models/TarifRaccourci.php",
        "app/Services/TarifService.php",
        "app/Http/Controllers/TarifController.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 5. LAYOUT + COMPOSANTS
    # ═══════════════════════════════════════════════════════════════
    "LAYOUT et COMPOSANTS": [
        "resources/views/layouts/cyber-manager.blade.php",
        "resources/views/components/kpi-card.blade.php",
        "resources/views/components/badge.blade.php",
        "resources/views/components/icone.blade.php",
        "resources/views/components/flash.blade.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 6. ROUTES ET CONFIG
    # ═══════════════════════════════════════════════════════════════
    "ROUTES et CONFIG": [
        "routes/web.php",
        "routes/console.php",
        "config/mikrotik.php",
    ],

    # ═══════════════════════════════════════════════════════════════
    # 7. DASHBOARD (pour cohérence des stats)
    # ═══════════════════════════════════════════════════════════════
    "DASHBOARD": [
        "app/Http/Controllers/DashboardController.php",
        "app/Services/DashboardService.php",
    ],
}

def taille_lisible(o):
    if o < 1024: return f"{o} o"
    if o < 1048576: return f"{o/1024:.1f} Ko"
    return f"{o/1048576:.2f} Mo"

def lire(p):
    try:
        t = p.stat().st_size
        if t > TAILLE_MAX_KO * 1024:
            return False, f"[TROP VOLUMINEUX — {taille_lisible(t)}]", t
        return True, p.read_text(encoding="utf-8"), t
    except UnicodeDecodeError:
        return False, "[ENCODAGE NON UTF-8]", 0
    except Exception as e:
        return False, f"[ERREUR : {e}]", 0

def generer():
    debut = datetime.now()
    print("=" * 60)
    print("  GÉNÉRATION GENERATOR.txt — Cyber Manager")
    print("=" * 60)

    stats = {"total": 0, "trouves": 0, "manquants": 0, "erreurs": 0, "octets": 0}
    manquants, erreurs, lignes = [], [], []

    lignes.append("╔" + "═" * 76 + "╗")
    lignes.append("║" + " CYBER MANAGER — GENERATOR.txt ".center(76) + "║")
    lignes.append("║" + f" Généré le {debut.strftime('%d/%m/%Y à %H:%M:%S')} ".center(76) + "║")
    lignes.append("╚" + "═" * 76 + "╝")

    for titre, fichiers in SECTIONS.items():
        lignes.append("\n\n" + "═" * 78)
        lignes.append(f"  {titre}")
        lignes.append("═" * 78 + "\n")

        for rel in fichiers:
            abs_p = RACINE / rel
            stats["total"] += 1
            lignes.append("\n" + "─" * 78)
            lignes.append(f"📄  {rel}")
            lignes.append("─" * 78 + "\n")

            if not abs_p.exists():
                stats["manquants"] += 1
                manquants.append(rel)
                lignes.append(f"[FICHIER MANQUANT — {abs_p}]\n")
                continue

            ok, contenu, taille = lire(abs_p)
            lignes.append(f"# Taille : {taille_lisible(taille)}  |  Statut : {'OK' if ok else 'ERREUR'}\n")
            lignes.append(contenu + "\n")

            if ok:
                stats["trouves"] += 1
                stats["octets"] += taille
            else:
                stats["erreurs"] += 1
                erreurs.append((rel, contenu))

    fin = datetime.now()
    duree = (fin - debut).total_seconds()

    lignes.append("\n\n" + "═" * 78)
    lignes.append("  RÉCAPITULATIF")
    lignes.append("═" * 78)
    lignes.append(f"  Total demandés   : {stats['total']}")
    lignes.append(f"  Trouvés et inclus: {stats['trouves']}")
    lignes.append(f"  Manquants        : {stats['manquants']}")
    lignes.append(f"  En erreur        : {stats['erreurs']}")
    lignes.append(f"  Poids code       : {taille_lisible(stats['octets'])}")
    lignes.append(f"  Durée génération : {duree:.2f} s")
    lignes.append("═" * 78)

    if manquants:
        lignes.append("\n  ⚠ FICHIERS MANQUANTS :")
        for m in manquants:
            lignes.append(f"    - {m}")
    if erreurs:
        lignes.append("\n  ⚠ FICHIERS EN ERREUR :")
        for c, e in erreurs:
            lignes.append(f"    - {c} : {e}")

    lignes.append("\n  Fin du GENERATOR.txt.\n")

    try:
        FICHIER_SORTIE.write_text("\n".join(lignes), encoding="utf-8")
    except Exception as e:
        print(f"\n❌ Impossible d'écrire {FICHIER_SORTIE} : {e}")
        sys.exit(1)

    print(f"\n✅ GENERATOR.txt généré.")
    print(f"   {FICHIER_SORTIE}")
    print(f"   Poids     : {taille_lisible(FICHIER_SORTIE.stat().st_size)}")
    print(f"   Contenu   : {stats['trouves']} inclus, "
          f"{stats['manquants']} manquants, {stats['erreurs']} erreurs")
    print(f"   Durée     : {duree:.2f} s\n")

    if manquants:
        print("   Fichiers manquants :")
        for m in manquants:
            print(f"     - {m}")
        print()

if __name__ == "__main__":
    generer()