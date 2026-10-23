import os

def generer_contexte_portail():
    print("Génération du fichier GENERE_PORTAIL.txt...")
    fichier_sortie = "GENERE_PORTAIL.txt"
    
    fichiers_a_inclure = [
        "routes/web.php",
        "app/Http/Controllers/PortailController.php",
        "app/Models/PortailParametre.php",
        "app/Services/MikroTikService.php",
        "app/Services/TarifService.php",
        "app/Services/MikroTik/MikrotikConnection.php",
        "resources/views/portail/admin.blade.php",
        "resources/views/portail/login.blade.php",    # Le fichier moche à améliorer
        "resources/views/portail/dashboard.blade.php", # Le dashboard temps réel à améliorer
        "resources/views/layouts/cyber-manager.blade.php",
    ]
    
    # Ajouter les migrations liées au portail et aux tarifs
    migrations = [
        "database/migrations/2026_10_20_150538_create_portail_parametres_table.php",
        "database/migrations/2026_10_07_100000_extend_tarifs_for_rules.php"
    ]
    fichiers_a_inclure.extend(migrations)

    with open(fichier_sortie, "w", encoding="utf-8") as f:
        f.write("==================================================\n")
        f.write("   CODE SOURCE COMPLET - MODULE PORTAIL CAPTIF    \n")
        f.write("==================================================\n\n")
        
        for fichier in fichiers_a_inclure:
            if os.path.exists(fichier):
                f.write(f"\n{'='*80}\n")
                f.write(f"📄 {fichier}\n")
                f.write(f"{'='*80}\n\n")
                with open(fichier, "r", encoding="utf-8") as source:
                    f.write(source.read())
                f.write("\n\n")
            else:
                print(f"⚠️ Fichier introuvable : {fichier}")

    print(f"✅ Terminé ! Le fichier {fichier_sortie} est prêt.")

if __name__ == "__main__":
    generer_contexte_portail()