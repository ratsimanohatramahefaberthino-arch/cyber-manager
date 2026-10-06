# Étape 3 — Refonte HotSpot (ex-Vouchers)

## Commandes (dans l'ordre)
1. Sauvegarde : mysqldump -u root -p <nom_base> > backup_avant_step3.sql
2. Supprimer les fichiers listés dans la section 10 de la livraison
3. Retirer VOUCHER_POOL_MONTANT et VOUCHER_POOL_DUREE de .env
4. php artisan config:clear
5. php artisan migrate
6. npm run build (ou npm run dev)

## Tests manuels (MikroTik TEST uniquement)
1. HotSpot → Générer → quantité 5 → 5 identifiants disponibles, bannière verte,
   case d'en-tête pré-cochable → Imprimer → fenêtre d'impression navigateur.
2. HotSpot → Ajouter → remplir username/mahefa-test, password, cocher
   "Protéger" → créé, badge cadenas visible dans la liste.
3. Se connecter avec un identifiant généré (portail captif TEST) puis
   wifi:sync-sessions → état "En cours", appareil + volume se remplissent.
   Déconnecter, resynchroniser → état "Utilisé".
4. Tenter de supprimer l'identifiant protégé créé en (2) : le prompt exige
   de retaper le username exact ; une saisie incorrecte annule tout.
5. Sélection multiple incluant un protégé → Supprimer (masse) : le protégé
   est ignoré et compté séparément dans le message de résultat.
6. php artisan vouchers:reapprovisionner --simuler → fonctionne sans
   montant/durée.

## Points d'attention
1. Les colonnes montant/duree de `vouchers` existent toujours en base
   (retirer la table serait un chantier à part) ; elles valent 0 partout
   depuis cette interface, ce qui correspond déjà à "illimité" dans
   WifiSessionService depuis l'étape 1 — aucun autre fichier à toucher.
2. `code` vaut désormais systématiquement `username` ; l'ancienne
   contrainte d'unicité sur `password`/`code` n'a plus lieu d'être
   puisque username et password sont générés indépendamment.
3. La suppression tente d'abord de retirer le compte du MikroTik puis,
   si l'identifiant était en_cours, de déconnecter le client actif par sa
   MAC — les deux sont best-effort (loggués, jamais bloquants).
4. Un identifiant protégé supprimé en masse est ignoré, jamais supprimé :
   seule la suppression individuelle (avec saisie du username) fonctionne.
5. La page `/vouchers/permanents` disparaît : les filtres "Réutilisables"
   et "Protégés" de la liste principale couvrent le même besoin.
6. L'état de la sidebar (repliée/dépliée) est mémorisé en localStorage,
   par navigateur — chaque poste du cyber a donc sa propre préférence.
7. Rien n'a été ciblé vers 128.1.1.1 (PRODUCTION).

## Hors scope (pour la suite, onglet Wi-Fi)
- Facturation à la consommation réelle (remplace montant/durée figés).
- Détection plus fine du type d'appareil au moment de la génération
  (actuellement renseignée seulement une fois l'identifiant utilisé).