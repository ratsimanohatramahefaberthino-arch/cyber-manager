# -*- coding: utf-8 -*-
"""
projet_generator.py
Génère un fichier GENERATOR.txt contenant le contenu de tous les fichiers
listés ci-dessous, prêt à être envoyé à Claude.

Usage : py projet_generator.py
"""

import os
from pathlib import Path

# =============================================================
# CONFIGURATION — Modifie cette liste à chaque étape
# =============================================================

FILES = [
    # Services Wi-Fi
    "app/Services/MikroTikService.php",
    "app/Services/MikroTik/MikrotikConnection.php",
    "app/Services/WifiSessionService.php",
    "app/Services/VoucherService.php",
    "app/Services/VoucherReplenishmentService.php",
    "app/Services/SessionService.php",
    "app/Services/AgentWindowsService.php",

    # Modèles
    "app/Models/Voucher.php",
    "app/Models/Session.php",
    "app/Models/LotVoucher.php",
    "app/Models/AppareilWifi.php",
    "app/Models/Poste.php",

    # Controllers
    "app/Http/Controllers/VoucherController.php",
    "app/Http/Controllers/WifiController.php",
    "app/Http/Controllers/SessionController.php",
    "app/Http/Controllers/DashboardController.php",

    # Vues — Vouchers
    "resources/views/vouchers/index.blade.php",
    "resources/views/vouchers/create.blade.php",

    # Vues — Layout et composants
    "resources/views/layouts/cyber-manager.blade.php",

    # Config
    "config/mikrotik.php",

    # Routes
    "routes/web.php",
    "routes/console.php",

    # Commands
    "app/Console/Commands/WifiSyncSessionsCommand.php",
    "app/Console/Commands/MikrotikTestCommand.php",

    # Migrations
    "database/migrations/2026_09_25_093115_create_vouchers_table.php",
    "database/migrations/2026_09_25_093109_create_lot_vouchers_table.php",
    "database/migrations/2026_09_28_200000_add_wifi_fields_to_sessions_table.php",
]

OUTPUT_FILE = "GENERATOR.txt"
SEPARATOR = "=" * 80

# =============================================================
# NE PAS MODIFIER EN DESSOUS SAUF SI NÉCESSAIRE
# =============================================================

def main():
    base_dir = Path(__file__).resolve().parent
    output_path = base_dir / OUTPUT_FILE

    total_found = 0
    total_missing = 0
    total_lines = 0

    with open(output_path, "w", encoding="utf-8") as out:
        # En-tête
        out.write(SEPARATOR + "\n")
        out.write("GENERATOR — Contenu des fichiers du projet Cyber Manager\n")
        out.write(SEPARATOR + "\n\n")
        out.write(f"Projet : {base_dir}\n")
        out.write(f"Nombre de fichiers demandés : {len(FILES)}\n\n")
        out.write("Ce document contient le code source actuel des fichiers\n")
        out.write("concernés par l'étape en cours. Il doit être envoyé à Claude\n")
        out.write("en complément du prompt d'étape.\n\n")

        # Sommaire
        out.write(SEPARATOR + "\n")
        out.write("SOMMAIRE\n")
        out.write(SEPARATOR + "\n\n")
        for i, rel in enumerate(FILES, 1):
            marker = ""
            full = base_dir / rel
            if not full.exists():
                marker = "  [MANQUANT]"
            out.write(f"  {i:3d}. {rel}{marker}\n")
        out.write("\n")

        # Contenu
        for rel in FILES:
            full_path = base_dir / rel
            out.write(SEPARATOR + "\n")
            out.write(f"FICHIER : {rel}\n")
            out.write(SEPARATOR + "\n\n")

            if not full_path.exists():
                out.write("[FICHIER INTROUVABLE — ignoré]\n\n")
                total_missing += 1
                continue

            try:
                with open(full_path, "r", encoding="utf-8") as f:
                    content = f.read()
                out.write(content)
                if not content.endswith("\n"):
                    out.write("\n")
                total_found += 1
                total_lines += content.count("\n") + 1
            except UnicodeDecodeError:
                # Fallback : lire en latin-1 (rare sur du code, mais au cas où)
                try:
                    with open(full_path, "r", encoding="latin-1") as f:
                        content = f.read()
                    out.write(content)
                    if not content.endswith("\n"):
                        out.write("\n")
                    total_found += 1
                    total_lines += content.count("\n") + 1
                except Exception as e:
                    out.write(f"[ERREUR DE LECTURE : {e}]\n\n")
                    total_missing += 1
            except Exception as e:
                out.write(f"[ERREUR DE LECTURE : {e}]\n\n")
                total_missing += 1

            out.write("\n\n")

    # Résumé terminal
    print()
    print("=" * 60)
    print(f"  GENERATOR.txt généré : {output_path}")
    print(f"  Fichiers trouvés    : {total_found}")
    print(f"  Fichiers manquants  : {total_missing}")
    print(f"  Lignes totales      : {total_lines}")
    print(f"  Taille              : {output_path.stat().st_size / 1024:.1f} Ko")
    print("=" * 60)
    print()

    if total_missing > 0:
        print(f"  ATTENTION : {total_missing} fichier(s) manquant(s).")
        print("  Vérifie les chemins dans la liste FILES.")
        print()


if __name__ == "__main__":
    main()