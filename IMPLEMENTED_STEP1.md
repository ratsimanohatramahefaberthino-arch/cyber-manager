# Étape 1 — Fondations données Wi-Fi

## Fichiers créés
- database/migrations/2026_09_28_210000_create_appareils_wifi_table.php
- database/migrations/2026_09_28_210100_extend_vouchers_for_wifi.php
- database/migrations/2026_09_28_210200_add_ssid_and_appareil_to_cyber_sessions.php
- app/Models/AppareilWifi.php
- database/seeders/VoucherPermanentSeeder.php

## Fichiers modifiés
- app/Models/Voucher.php (type, appareil, scopes, estReutilisable, constantes)
- app/Models/Session.php (ssid, appareil_wifi_id, relation appareilWifi)
- app/Services/WifiSessionService.php
- app/Services/VoucherService.php
- config/mikrotik.php (ssid_default, ssid_map)

## .env à compléter (TEST uniquement)
MIKROTIK_SSID_MAP=hotspot1=NomDuSSID
MIKROTIK_SSID_DEFAULT=NomDuSSID
(valeurs à adapter au nom réel du serveur HotSpot : /ip hotspot print)

## Commandes (dans l'ordre, depuis E:\xampp\htdocs\cyber-manager)
1. Sauvegarde : mysqldump -u root -p <nom_base> > backup_avant_step1.sql
2. php artisan config:clear
3. php artisan migrate --pretend      (optionnel : voir le SQL)
4. php artisan migrate
5. php artisan db:seed --class=VoucherPermanentSeeder
6. php artisan wifi:sync-sessions     (avec un client connecté sur le TEST)
Rollback : php artisan migrate:rollback --step=3

## Tests tinker (php artisan tinker)
// Structure
Schema::getColumnListing('appareils_wifi');
DB::select("SHOW COLUMNS FROM vouchers LIKE 'etat'");     // enum(...6 valeurs...)
DB::select("SHOW COLUMNS FROM vouchers LIKE 'type_voucher'");
Schema::hasColumns('cyber_sessions', ['ssid','appareil_wifi_id']);   // true

// Vouchers existants intacts, tous en 'temporaire'
App\Models\Voucher::porteeTemporaires()->count();
App\Models\Voucher::whereNull('type_voucher')->count();  // 0

// Seeder
App\Models\Voucher::porteePermanents()->get(['username','etat','duree','mikrotik_synced_at']);
App\Models\Voucher::porteePermanents()->first()->estReutilisable();  // true
App\Models\Voucher::porteeTemporaires()->first()?->estReutilisable(); // false

// AppareilWifi
$a = App\Models\AppareilWifi::trouverOuCreerParMac('aa-bb-cc-dd-ee-ff', ['host_name' => 'test']);
$a->adresse_mac;                       // AA:BB:CC:DD:EE:FF
$b = App\Models\AppareilWifi::trouverOuCreerParMac('AA:BB:CC:DD:EE:FF', ['host_name' => null]);
$b->id === $a->id;                     // true, host_name 'test' conservé
$b->toucherDerniereConnexion(); $b->fresh()->derniere_connexion;
$a->delete();                          // nettoyage

// Après un wifi:sync-sessions avec un client connecté
$s = App\Models\Session::where('type_session','wifi')->latest('id')->first();
$s->ssid; $s->appareil_wifi_id; $s->appareilWifi?->adresse_mac;
$s->voucher?->etat;                    // en_cours pendant la session
// Déconnecter le client puis relancer wifi:sync-sessions :
// temporaire → utilise ; permanent → disponible

## Points d'attention
1. vouchers.etat était un VARCHAR (pas un ENUM). La migration le convertit ;
   elle refuse de tourner (message explicite) si une valeur hors liste existe.
2. date_expiration n'a pas été créé : la colonne existante
   date_heure_expiration fait office.
3. Les 2 permanents du seeder ont mikrotik_synced_at = NULL. Le bouton
   "Synchroniser tout" (VoucherController@synchroniserTout) les créerait sur
   le MikroTik courant (sans limit-uptime). OK sur le TEST ; à ne JAMAIS
   déclencher contre la PRODUCTION.
4. Changements de comportement dans WifiSessionService :
   - un voucher passe à en_cours à l'ouverture (avant : utilise directement),
     puis utilise/expire (temporaire) ou disponible (permanent) à la fermeture ;
   - date_heure_expiration n'est plus posée à chaque fermeture (elle signifie
     "validité dépassée", posée uniquement à l'expiration réelle) ;
   - durée prévue = 0 (permanent, compte sans voucher) → session "terminee",
     jamais "expiree" ;
   - une entrée active défectueuse est ignorée (loggée) sans bloquer la sync ;
   - parseUptime gère les semaines ("1w2d...") présentes en RouterOS 6 et 7.
5. Volumes : mapping conservé (entrée = download client = bytes-out ;
   sortie = upload client = bytes-in). Inversion possible dans calculerVolumes().
6. SSID : déduit du serveur HotSpot via MIKROTIK_SSID_MAP (le vrai SSID radio
   n'est pas exposé par /ip/hotspot/active). Sans configuration, la valeur
   est le nom du serveur HotSpot.
7. host_name vient des baux DHCP ; type_appareil / fabricant / modèle / OS /
   user_agent restent vides (inconnus côté MikroTik) : à alimenter plus tard
   (OUI MAC, portail captif).
8. MAC aléatoires (iOS/Android "adresse privée") : un même téléphone peut
   créer plusieurs AppareilWifi. Limitation inhérente au réseau.
9. Les sessions Wi-Fi déjà en base n'ont pas d'appareil_wifi_id (pas de
   rétro-remplissage). Les sessions encore ouvertes seront rattachées à
   la prochaine sync.
10. VoucherController (filtre etat, stats) et les vues ne connaissent pas encore
    en_cours / expire / desactive et le type : à traiter dans l'étape UI.
11. config/mikrotik.php garde 128.0.1.1 comme valeur par défaut de MIKROTIK_HOST
    (IP TEST en dur). À vider à terme pour respecter la règle 1.
12. Aucune commande n'a été ciblée vers 128.1.1.1 (PRODUCTION).