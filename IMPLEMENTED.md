# IMPLEMENTED — Phase 1 : Socle MikroTik

Date : 2026-09-28
Statut : à tester sur MikroTik TEST (128.0.1.1:8728)

## Objectif
Établir une connexion réelle et fiable entre Laravel et l'API RouterOS,
avec configuration externalisée et testable en une commande.

## Fichiers créés
- `config/mikrotik.php`
- `app/Services/MikroTik/MikrotikConnection.php`
- `app/Console/Commands/MikrotikTestCommand.php`
- `IMPLEMENTED.md`

## Fichiers modifiés
- `app/Services/MikroTikService.php` (remplace le stub vide)
- `app/Providers/AppServiceProvider.php` (binding singleton)
- `.env.example` (ajout variables MIKROTIK_*)

## Migrations
Aucune.

## Commandes à exécuter
1. Copier les variables MikroTik dans `.env` :
   MIKROTIK_HOST=128.0.1.1
   MIKROTIK_PORT=8728
   MIKROTIK_USER=<user>
   MIKROTIK_PASSWORD=<pass>
   MIKROTIK_SSL=false
   MIKROTIK_TIMEOUT=5
   MIKROTIK_ENV=test

2. Vider le cache config :
   php artisan config:clear

3. Lancer :
   php artisan mikrotik:test
   php artisan mikrotik:test --users --active --profiles

## Résultats attendus
- Affichage de l'identité du routeur (`INSIDE`)
- Version RouterOS (ex: 7.21.3)
- Board name (ex: hAP ac²)

## Problèmes restants / prochaines étapes
- Tester avec RouterOS 6.49.17 (production) en lecture seule, SANS modifier.
- Ajouter la journalisation (option debug) dans MikrotikConnection.
- Préparer la Phase 3 : lecture seule HotSpot depuis l'UI.
- Ne pas brancher VoucherService au MikroTik avant validation du test.

## Phase 3a — HotSpot user (créer / chercher / supprimer)

Date : 2026-09-28
Statut : à valider sur MikroTik TEST (INSIDE)

### Objectif
Prouver qu'on peut créer, retrouver et supprimer un utilisateur HotSpot
depuis Laravel, sans toucher au reste du système.

### Fichiers modifiés
- `config/mikrotik.php` (+ hotspot_profile, hotspot_user_prefix)
- `app/Services/MikroTikService.php`
    - ajout createHotspotUser(), findHotspotUser(), deleteHotspotUser(),
      listCyberManagerHotspotUsers()
    - refactor query() → query() / queryFiltered() / queryWithId() / send()
- `.env` + `.env.example` (+ MIKROTIK_HOTSPOT_PROFILE, MIKROTIK_USER_PREFIX)

### Fichiers créés
- `app/Console/Commands/MikrotikUserTestCommand.php`

### Commande de test
    php artisan config:clear
    php artisan mikrotik:user-test

### Comportement attendu
- Crée `cm-TEST-XXXXXX` (profil `default`, `limit-uptime=5m`)
- Le retrouve via `/ip/hotspot/user/print ?name=...`
- Le supprime
- Affiche "Test complet réussi"

### Convention actée
- Préfixe `cm-` pour tous les users créés par Cyber Manager.
- Profil par défaut : `default` (configurable par .env).

### Prochaine étape (Phase 3b)
- Brancher VoucherService → MikroTikService avec transaction DB + API.

## Phase 3b — VoucherService branché sur MikroTik

Date : 2026-09-28
Statut : à valider

### Objectif
Un voucher créé dans Cyber Manager doit aussi apparaître
comme user HotSpot sur le MikroTik.

### Stratégie
- DB d'abord (source de vérité métier)
- Sync MikroTik ensuite, non bloquante
- Échec → voucher marqué non synchronisé, retentable via commande

### Fichiers créés
- `database/migrations/2026_09_28_150000_add_mikrotik_sync_to_vouchers_table.php`
- `app/Console/Commands/MikrotikSyncVouchersCommand.php`

### Fichiers modifiés
- `app/Models/Voucher.php` (+ mikrotik_synced_at, mikrotik_sync_error)
- `app/Services/VoucherService.php`
    - injection de MikroTikService
    - +synchroniserVersMikroTik()
    - +synchroniserVouchersEnAttente()
    - creerVoucher() : DB puis sync
    - creerLot() : DB transaction puis sync en série
    - annuler() : supprime aussi le user HotSpot
- `app/Services/MikroTikService.php` (+ formatUptime())

### Commandes à exécuter
    php artisan migrate
    php artisan config:clear
    php artisan mikrotik:sync-vouchers

### Tests prévus
1. Créer un voucher via tinker → vérifier sur MikroTik
2. Créer un lot de 3 vouchers → vérifier les 3 sur MikroTik
3. Simuler un échec (mauvais mdp) → vérifier mikrotik_sync_error
4. Retenter avec mikrotik:sync-vouchers → vérifier la récupération

### Prochaine étape (Phase 3c)
- UI : page Vouchers qui affiche l'état de synchronisation MikroTik

## Phase 4 — Dashboard avec données réelles

Date : 2026-09-28
Statut : à valider

### Objectif
Remplacer le DashboardController stub par un dashboard branché sur :
- l'API MikroTik (state live)
- la base Cyber Manager (postes, vouchers, sessions)

### Fichiers créés
- `app/Services/DashboardService.php`

### Fichiers modifiés
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php` (réécrit, étend `layouts.cyber-manager`)

### Comportement
- Ping MikroTik en live à chaque chargement (timeout = MIKROTIK_TIMEOUT).
- Si MikroTik injoignable → bandeau rouge, page reste fonctionnelle,
  stats locales (postes/vouchers/sessions) toujours affichées.
- Si MikroTik joignable → bandeau vert + stats HotSpot complètes.

### Tests prévus
1. MikroTik TEST allumé → dashboard OK, données cohérentes
2. `php artisan mikrotik:test` échoue (ex: mauvais user) → dashboard
   affiche bandeau rouge sans planter
3. Créer un poste + un voucher en DB → dashboard reflète les nouveaux comptes

### Notes / dette technique
- `MIKROTIK_TIMEOUT=5` : si INSIDE est éteint, le dashboard peut
  mettre jusqu'à 5s à charger. À optimiser plus tard (cache 30s).
- `derniere_synchronisation` sur `vouchers` est maintenant inutilisée ;
  à supprimer dans une migration de nettoyage plus tard.

### Prochaine étape
- Phase 5 (option A) : brancher SessionService sur le HotSpot MikroTik
  (décompte temps réel, déconnexion active)
- Phase 5 (option B) : UI Vouchers (liste, création de lot, impression)

## Phase 5B — UI Vouchers

Date : 2026-09-28
Statut : à valider

### Objectif
Rendre la gestion des vouchers utilisable sans terminal :
- liste avec filtres et pagination
- création de lot en un formulaire
- synchronisation unitaire et globale vers MikroTik
- annulation (DB + suppression HotSpot)

### Fichiers créés
- `app/Http/Controllers/VoucherController.php`
- `resources/views/vouchers/index.blade.php`
- `resources/views/vouchers/create.blade.php`

### Fichiers modifiés
- `routes/web.php` (6 routes vouchers sous middleware auth)
- `resources/views/layouts/cyber-manager.blade.php` (lien Vouchers actif)

### Routes ajoutées
- GET    /vouchers                     vouchers.index
- GET    /vouchers/create              vouchers.create
- POST   /vouchers                     vouchers.store
- POST   /vouchers/synchroniser-tout   vouchers.synchroniser-tout
- POST   /vouchers/{voucher}/synchroniser vouchers.synchroniser
- DELETE /vouchers/{voucher}           vouchers.annuler

### Tests prévus
1. /vouchers → liste des 3 vouchers avec état de sync
2. /vouchers/create → créer un lot de 3 (500 Ar / 25 min)
3. Vérifier que les 3 apparaissent sur le MikroTik
4. Cliquer "Sync" sur un voucher non synchronisé → doit passer en vert
5. Cliquer "Annuler" sur un voucher → disparaît du MikroTik + badge "annule"
6. Filtrer par "Non synchronisés" → vue correcte

### Prochaine étape (Phase 5A)
- Sessions Wi-Fi branchées sur MikroTik (décompte, déconnexion active)

## Phase 6a — Supervision Wi-Fi

Date : 2026-09-28
Statut : à valider

### Objectif
Voir en direct depuis l'interface :
- les clients HotSpot actuellement connectés
- les appareils connus du HotSpot
- les baux DHCP (tous appareils du réseau)

### Fichiers créés
- `app/Http/Controllers/WifiController.php`
- `resources/views/wifi/index.blade.php`

### Fichiers modifiés
- `app/Services/MikroTikService.php` (+listDhcpLeases, +listHotspotHosts, +findHotspotActive)
- `routes/web.php` (+route wifi.index)
- `resources/views/layouts/cyber-manager.blade.php` (lien Wi-Fi actif)

### Commandes read-only utilisées
- /ip/hotspot/active/print
- /ip/hotspot/host/print
- /ip/dhcp-server/lease/print

Aucune commande destructive.

### Tests prévus
1. /wifi → 3 onglets avec les compteurs corrects
2. Brancher un téléphone en Wi-Fi et se connecter au HotSpot
3. Actualiser /wifi → l'appareil apparaît dans "Clients connectés"
4. Vérifier que les baux DHCP listent bien tous les appareils

### Prochaine étape (Phase 6b)
- Sessions Wi-Fi : quand un voucher est utilisé côté HotSpot,
  créer une Session en base et démarrer le décompte

## Phase 6b — Sessions Wi-Fi automatiques

Date : 2026-09-28
Statut : à valider

### Objectif
Quand un client se connecte via un voucher HotSpot, créer automatiquement
une Session en base, lier le voucher, suivre le décompte, fermer à la
déconnexion. Aucune écriture sur le MikroTik : lecture seule.

### Fichiers créés
- `database/migrations/2026_09_28_200000_add_wifi_fields_to_sessions_table.php`
- `app/Services/WifiSessionService.php`
- `app/Console/Commands/WifiSyncSessionsCommand.php`

### Fichiers modifiés
- `app/Models/Session.php` (+5 champs, +relation voucher())
- `routes/console.php` (schedule wifi:sync-sessions everyMinute)

### Logique
1. Lit /ip/hotspot/active
2. Pour chaque actif :
   - déja en base par mikrotik_active_id → met à jour uptime/bytes
   - sinon → crée une Session type_session='wifi', lie le voucher
3. Ferme les Sessions en_cours dont le .id n'est plus actif
   → 'expiree' si temps_restant <= 0, sinon 'terminee'

### Commandes
- php artisan wifi:sync-sessions  (manuel)
- php artisan schedule:work        (auto toutes les minutes)

### Tests prévus
1. Connecter un téléphone avec un voucher
2. Lancer wifi:sync-sessions → une session apparaît dans /sessions
3. Vérifier que le voucher passe en 'utilise'
4. Laisser le temps s'écouler, vérifier la mise à jour uptime
5. Déconnecter le Wi-Fi du téléphone
6. Relancer wifi:sync-sessions → session fermée en 'terminee'

### Prochaine étape (Phase 6c)
- Actions Wi-Fi : bouton "Déconnecter" un client actif depuis /wifi
- Activation/désactivation d'un voucher HotSpot

## Phase 6c — Actions Wi-Fi

Date : 2026-09-28
Statut : à valider

### Objectif
Permettre depuis l'UI :
- déconnecter un client actif
- désactiver / réactiver un compte HotSpot
- supprimer un compte HotSpot
- lancer une synchronisation manuelle des sessions

### Fichiers modifiés
- `app/Services/MikroTikService.php`
    + disconnectHotspotActiveByMac()
    + setHotspotUserDisabled()
- `app/Http/Controllers/WifiController.php`
    + syncNow() / deconnecter() / toggleCompte() / supprimerCompte()
- `routes/web.php`
    + 4 routes sous middleware auth
- `resources/views/wifi/index.blade.php`
    + flash messages
    + bouton "Synchroniser maintenant"
    + colonne Actions (Déconnecter)
- `resources/views/wifi/comptes.blade.php`
    + flash messages
    + colonne Actions (Désactiver / Supprimer)

### Commandes MikroTik utilisées (écriture)
- /ip/hotspot/active/remove
- /ip/hotspot/user/set =disabled=yes|no
- /ip/hotspot/user/remove

### Points d'attention
- Après déconnexion d'un client, la session en base se ferme
  à la prochaine sync (jusqu'à 1 min de délai).
  → Compromis assumé : pas de fermeture immédiate via webhook.

### Prochaine étape (Phase 6d)
- Extension / recharge d'un voucher actif (ajouter du temps à chaud)