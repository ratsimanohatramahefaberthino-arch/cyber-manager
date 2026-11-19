# CAHIER DES CHARGES D'ARCHITECTURE & PLAN D'EXÉCUTION TECHNIQUE
## Cyber Manager : Serveur autonome, Lanceur & Résilience aux coupures

| | |
|---|---|
| **Statut** | Document de référence validé : feuille de route définitive |
| **Date de rédaction** | 19 novembre 2026 |
| **Périmètre** | Installateur serveur, exécutable serveur (service + télécommande), résilience aux coupures de courant |
| **Hors périmètre** | Client Windows (Gardien / Écran / `CyberManagerInstall.exe`) : déjà livré, il n'est modifié ici que pour l'affichage « session conservée » (§3.6) |

### Mode d'emploi de ce document (pour toute nouvelle session ou autre IA)

1. Ce document est **autoporteur** : il suffit de le lire en entier avant de coder.
2. Ne jamais sauter d'étape : chaque étape (§5) a une **Définition du « Terminé »** (DoD) à valider avant la suivante.
3. Les **règles d'or (§1.2)** priment sur toute commodité technique. En cas de conflit, la règle gagne.
4. Les éléments marqués **[DÉCISION ACTÉE]** ne se rediscutent pas ; ceux marqués **[À CONFIRMER]** sont listés en Annexe C.
5. Convention de livraison de code : fichiers **complets** (jamais de « ... »), chemin indiqué, un commit par sous-lot, commandes Windows (PowerShell) en fin de réponse.
6. Après chaque sous-lot terminé, mettre à jour l'**Annexe D (suivi d'avancement)**.

---

# 1. CONTEXTE, ENJEUX ET RÈGLES D'OR

## 1.1 Contexte de production

- **Cyber Manager** est un logiciel complet de gestion de cybercafé, **déployé dans un cybercafé réel à Madagascar** (plus de 5 ans d'exploitation quotidienne sur le terrain).
- Composants existants :
  - **Serveur central** : Laravel (`composer.json` exige `^12`), PHP 8.2+, Tailwind CSS, API pour postes et Wi-Fi.
  - **Postes clients Windows** : un Service Windows **Gardien** (.NET C#) qui supervise une application plein écran **Écran / Agent** (WinForms + WebView2). Installation client 100 % automatisée par `CyberManagerInstall.exe`.
  - **Liaison réseau optionnelle** avec un routeur **MikroTik RouterOS** (HotSpot / Walled Garden) : parfois présent, parfois absent.
- Stack serveur actuelle (développement) : XAMPP (Apache + MariaDB), `php artisan serve`, Composer manuel. **C'est ce qui doit disparaître en production.**
- **Le gérant n'est pas un développeur.** Il ne tape aucune commande, n'ouvre pas Winbox, ne touche pas aux dossiers système.
- **Coupures d'électricité (délestage Jirama) fréquentes et brutales**, parfois plusieurs par jour. Scénario de référence : 5 PC en session payante, coupure en pleine journée, retour du courant 45 minutes plus tard.
- Référence d'ergonomie : l'outil **Mikhmon Server** actuellement utilisé par le gérant (petite fenêtre, bouton « Start Server », bouton « Open », démarre tout en silence et ouvre le navigateur).
- Fuseau horaire d'exploitation : `Indian/Antananarivo` (UTC+3, sans heure d'été).

## 1.2 Les 4 règles absolues (non négociables)

### Règle 1 : Indépendance matérielle
- **Aucune adresse IP, aucun port, aucun nom de machine en dur.**
- Tout est détecté dynamiquement ou configurable depuis l'interface par le gérant.
- Fonctionne quel que soit le PC, le sous-réseau, **avec ou sans routeur MikroTik**.
- *Conséquence directe : les constantes actuelles `Poste::DELAI_HORS_LIGNE` (180 s), `Poste::DELAI_GRACE_AGENT` (180 s), le port UDP de découverte `47474`, la garde de redémarrage de 90 s et la tolérance `config/postes.php` deviennent des **réglages modifiables depuis l'interface** (§3.5).*

### Règle 2 : Simplicité pour le gérant (zéro terminal)
- Tout passe par des **interfaces graphiques** (installateur, télécommande, interface web).
- Zéro invite de commande, zéro manipulation de fichiers dans des dossiers système.

### Règle 3 : Fonctionner dans tous les cas
- PC neufs, PC déjà configurés, réinstallations, **PC à plusieurs cartes réseau**, serveur avec un ancien XAMPP présent, base existante à migrer.

### Règle 4 : Production réelle
- Pas de solution temporaire ni de bricolage : mécanismes **finaux, fiables, robustes**, testés sur le scénario de coupure brutale.
- Invariant financier universel : **le montant facturé = temps réellement consommé avant la coupure, ni plus ni moins.**

## 1.3 Pourquoi on abandonne l'environnement de développement

| Élément de dev | Problème en production | Remplacement |
|---|---|---|
| `php artisan serve` | Serveur intégré **mono-requête** : un MikroTik lent dans un heartbeat bloque tous les postes. `PHP_CLI_SERVER_WORKERS` non supporté sous Windows. Meurt avec la fenêtre. | **Caddy + pool `php-cgi.exe`** supervisés par un service Windows |
| XAMPP (panneau de contrôle) | Démarrage manuel, pas de supervision, services Apache/MySQL fragiles, chemins fixes (`C:\xampp`, `E:\xampp`) | Bundle autonome, service Windows en démarrage automatique |
| **MariaDB / InnoDB** | Peut refuser de redémarrer après coupure brutale et exiger une récupération manuelle impossible pour le gérant. Mot de passe/port à gérer. | **SQLite** (WAL, `synchronous=FULL`) : un seul fichier, aucun service, aucun port |
| Composer / Node manuels | Aucun intérêt chez le client, risque d'erreurs, dépendance réseau | `vendor/` et assets Vite **pré-construits** dans le bundle |
| Tâches planifiées PowerShell (`preparer-serveur.ps1`, `installer-decouverte.ps1`) | Exécution manuelle en administrateur, dépend d'un `XamppDir` | Intégrés à l'installateur et au service hôte |
| Scheduler Laravel via tâche Windows | Granularité minimale d'**1 minute** et fenêtres qui clignotent ; vos tâches tournent toutes les 5 et 10 secondes | `php artisan schedule:work` supervisé, sans fenêtre |

> **Piège identifié dans le code actuel** : `AgentController::heartbeat` appelle `AccesInternetService::reconcilierTous()` **dans la requête HTTP**. À sortir du chemin du heartbeat (déjà assuré toutes les 30 s par `postes:maintenance --reseau`).

> **Piège identifié dans le code actuel** : `lockForUpdate()` est **ignoré par SQLite**. Les verrous de ligne de `expirerSiNecessaire`, `deduireTempsHorsLigne` et `cloturerSiInjoignable` ne protègent plus rien tant que `transaction_mode` n'est pas `IMMEDIATE`.

---

# 2. ARCHITECTURE CIBLE DU SYSTÈME SERVEUR

## 2.1 Vue d'ensemble

```
┌──────────────────────── PC SERVEUR (Windows) ────────────────────────┐
│                                                                      │
│  Session Windows (si ouverte)      Session 0 (toujours, même sans    │
│  ┌──────────────────────────┐      personne connecté)               │
│  │ CyberManagerServer.exe   │      ┌──────────────────────────────┐  │
│  │ (Télécommande, tray)     │◄────►│ CyberManagerServerService    │  │
│  │ WinForms/WPF             │ Named│ (Service hôte .NET)          │  │
│  └──────────────────────────┘ Pipe │  ├─ Job Object (enfants)     │  │
│                                    │  ├─ caddy.exe  ──┐           │  │
│                                    │  ├─ php-cgi.exe ×N ◄─FastCGI │  │
│                                    │  ├─ php artisan schedule:work│  │
│                                    │  ├─ php artisan cyber:decouverte (UDP)│
│                                    │  └─ (queue:work si besoin)   │  │
│                                    └──────────────┬───────────────┘  │
│                                                   │                  │
│  C:\Program Files\CyberManager\  (code, lecture seule)               │
│  C:\ProgramData\CyberManager\    (données : .env, base, logs, ...)   │
└──────────────────────────────────────────────────────────────────────┘
        ▲  HTTP(S) LAN                       ▲ UDP découverte
        │                                    │
   Postes clients (Gardien/Écran)      Postes clients (recherche du serveur)
```

## 2.2 Architecture d'exécution : Caddy + pool `php-cgi.exe` + SQLite

### 2.2.1 Serveur web : Caddy [DÉCISION ACTÉE]
- **Un seul exécutable** `caddy.exe`, configuration **générée** (jamais écrite à la main) à chaque démarrage.
- Écoute sur l'interface et le port choisis (détectés ou configurés), `auto_https off`, `admin off`.
- Dirige les requêtes PHP vers un **pool de processus `php-cgi.exe`** en FastCGI sur `127.0.0.1` (ports internes **choisis dynamiquement** parmi les ports libres, donc Règle 1 respectée).
- Sert directement les fichiers statiques (`public/build`, assets) et le contenu `storage/app/public` via une règle `/storage/*` (**pas de lien symbolique**, qui exige des droits administrateur).
- Taille du pool : **entre 4 et 8 processus**, selon le nombre de cœurs ; variable `PHP_FCGI_MAX_REQUESTS` définie pour recycler les processus.
- **Plan B** (si Caddy pose problème en test de charge) : Apache Lounge + PHP thread-safe, même supervision.

### 2.2.2 PHP embarqué
- PHP Windows non thread-safe (pour `php-cgi`) **et** CLI pour `artisan`, avec uniquement les extensions nécessaires : `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `curl`, `fileinfo`, `sockets`, `intl` (si utilisée), `zip` (sauvegardes/diagnostic).
- `php.ini` **généré** : fuseau `Indian/Antananarivo`, `opcache` activé, `display_errors=Off`, `log_errors=On` vers `ProgramData\...\logs`.
- Runtime Visual C++ : détecté et installé silencieusement par l'installateur s'il manque.
- `vendor/` installé avec `composer install --no-dev --optimize-autoloader`, assets Vite compilés, **aucune dépendance à Composer/Node/XAMPP** chez le client.

### 2.2.3 Laravel en lecture seule dans `Program Files`
Le code est en lecture seule ; Laravel doit écrire ailleurs. Variables imposées par le service dans l'environnement :
- `APP_STORAGE_PATH` (via `useStoragePath()` dans `bootstrap/app.php`) → `%ProgramData%\CyberManager\storage`
- `DB_DATABASE` → `%ProgramData%\CyberManager\data\cyber.sqlite`
- `APP_SERVICES_CACHE`, `APP_PACKAGES_CACHE`, `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE` → `%ProgramData%\CyberManager\cache\`
- `.env` situé dans `%ProgramData%\CyberManager\config\`, généré par l'installateur (avec une `APP_KEY` **propre à chaque installation**).
- `LOG_CHANNEL=daily` avec rétention (14 à 30 jours), sinon le disque se remplit.
- Après tout changement de réglage structurant : régénérer `config:cache`.

### 2.2.4 SQLite optimisé [DÉCISION ACTÉE]
Valeurs cibles de `config/database.php` (connexion `sqlite`) :

```php
'sqlite' => [
    'driver' => 'sqlite',
    'url' => env('DB_URL'),
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    'busy_timeout' => 8000,          // ms : attendre un verrou au lieu d'échouer
    'journal_mode' => 'WAL',         // lecteurs et écrivain ne se bloquent plus
    'synchronous' => 'FULL',         // durabilité maximale (coupures de courant)
    'transaction_mode' => 'IMMEDIATE', // sérialise les écrivains : remplace lockForUpdate()
],
```

Règles associées :
- **Durabilité avant performance** : `synchronous=FULL`. La charge d'un cybercafé (quelques dizaines de postes) est très inférieure à la capacité de SQLite.
- **Sauvegardes** : avec WAL, copier seulement le fichier `.sqlite` donne une sauvegarde **incohérente**. Utiliser `VACUUM INTO 'chemin'` (ou l'API de backup en ligne), rotation des N dernières copies, destination **sur un autre disque ou une clé** (suggestion dans l'installateur).
- **Contrôle d'intégrité au démarrage** : `PRAGMA quick_check`. Si échec → proposer la restauration de la dernière sauvegarde valide, jamais un démarrage silencieux sur base douteuse.
- **MySQL/MariaDB** : le support reste dans le code (développement, cas particuliers) mais n'est **pas proposé** dans l'assistant d'installation.
- **Import depuis XAMPP** : commande `php artisan serveur:importer-mysql` (lecture de l'ancienne base, écriture dans SQLite) appelée par l'installateur quand un ancien XAMPP/une ancienne base est détecté. Sans cela, les réinstallations perdent l'historique (Règle 3).
- Prérequis : **audit de compatibilité SQLite de toutes les migrations** (modifications de colonnes, `enum`, `dropColumn`, index) → étape 1.0.

## 2.3 Le Service Windows Hôte : `CyberManagerServerService`

### 2.3.1 Rôle
Seul composant qui **fait le travail**. Il vit en session 0, démarre avec Windows **sans que personne ne se connecte**, et supervise tout.

- Type : .NET Worker Service (`Microsoft.Extensions.Hosting.WindowsServices`).
- Démarrage : **automatique, non différé** (le délai de grâce agent est de 180 s : un démarrage différé peut verrouiller tous les postes).
- Compte : compte de service virtuel `NT SERVICE\CyberManagerServer` (moindre privilège), avec droits Modifier sur `%ProgramData%\CyberManager\` uniquement. Écoute du port 80 possible sans droits d'administrateur.
- Récupération sur échec : redémarrage à 5 s, 15 s, puis 60 s ; compteur remis à zéro au bout d'une journée.

### 2.3.2 Séquence de démarrage (ordre strict)

1. **Instance unique** (mutex global) ; lecture de `serveur.json` (configuration locale).
2. **Garde d'horloge** : attendre la synchronisation de l'heure Windows (borné, 120 s max) ; détecter une date aberrante (voir §3.4).
3. **Contrôle de la base** : `PRAGMA quick_check` ; en cas d'échec, état `BASE_A_RESTAURER` (pas de démarrage web).
4. **Détection d'arrêt anormal** (§3.2) : lecture du drapeau `arret_propre` et de `server_alive_at`, exécution de `php artisan serveur:demarrage` (qui crée l'incident et fige les sessions).
5. Si la version de l'application a changé : **sauvegarde automatique** puis `php artisan migrate --force`.
6. `php artisan serveur:preparer` : détection d'interface/port, choix des ports FastCGI, génération de `Caddyfile`, `php.ini` et `runtime.json` (§5, étape 2).
7. Lancement des enfants **sans fenêtre** (`CreateNoWindow`), tous rattachés à un **Job Object** (si le service meurt, Windows tue les enfants : pas de PHP orphelin qui garde le port).
8. **Test de santé** HTTP (`/api/sante`) ; état `PRET` seulement si OK.
9. Écriture de l'heure de démarrage et du drapeau `arret_propre = false`.

### 2.3.3 Processus supervisés

| Processus | Rôle | Notes |
|---|---|---|
| `caddy.exe` | Serveur web | Config générée |
| `php-cgi.exe` × N | Exécution de l'application | Ports internes dynamiques |
| `php artisan schedule:work` | **Planificateur Laravel** (expiration des forfaits, maintenance, sync Wi-Fi, vouchers) | Remplace les tâches Windows ; tourne en continu, compatible avec les cadences de 5 s et 10 s |
| `php artisan cyber:decouverte` | Écoute UDP de découverte des postes | Plus de tâche planifiée séparée ; port UDP **configurable** ; répond avec l'adresse de **l'interface qui a reçu la requête** (support multi-cartes, Règle 3) |
| `php artisan queue:work` | Jobs en file (si utilisés) | Optionnel |
| Sauvegarde quotidienne | `serveur:sauvegarder` | Déclenchée en interne à l'heure configurée |

> Amélioration ultérieure (non bloquante) : `wifi:sync-sessions` toutes les 5 s relance PHP ~12 fois par minute ; une boucle démon qui garde l'application chargée serait plus légère.

### 2.3.4 Surveillance
- **Santé web** : appel de l'URL de santé toutes les 10 s.
- **Planificateur** : un « dernier tick » (`server_alive_at`, §3.2) écrit par une tâche planifiée ; s'il a plus de 30 s de retard, le service redémarre `schedule:work`.
- **Redémarrage avec temporisation croissante** pour chaque enfant ; après N échecs consécutifs, état `EN_PANNE` avec message lisible (pas de boucle infinie).
- **Arrêt propre** : laisser finir les requêtes, écrire le drapeau `arret_propre = true`, arrêter les enfants dans l'ordre inverse.
- Journalisation du service dans `ProgramData\...\logs\service-AAAAMMJJ.log` (rotation).

### 2.3.5 Canal de contrôle : Named Pipe
- Pipe nommé local `\\.\pipe\CyberManagerServer`, ACL : lecture du statut pour les utilisateurs authentifiés ; actions sensibles (arrêt, redémarrage, restauration) réservées aux administrateurs Windows **ou** exigeant une confirmation dans la télécommande.
- Protocole : messages JSON ligne par ligne, versionné (`"v":1`).
- Aucun secret (mot de passe, token) ne transite par le pipe ni n'est journalisé.

## 2.4 La Télécommande Bureau : `CyberManagerServer.exe`

### 2.4.1 Rôle
Petite application WinForms (ou WPF) lancée à l'ouverture de session, icône dans la zone de notification. C'est une **télécommande** : elle **n'héberge rien**. La fermer n'arrête pas le serveur et elle le dit clairement (« Le serveur continue de fonctionner en arrière-plan »).

### 2.4.2 Fenêtre de contrôle (type Mikhmon Server)

**Indicateurs**
- **Grand voyant global** : vert (tout fonctionne), orange (dégradé : ex. MikroTik injoignable, sauvegarde ancienne), rouge (serveur arrêté ou en panne).
- **Adresse(s) du serveur** : une par interface réseau, avec **QR code** et bouton Copier. Exemple d'affichage : `http://192.168.x.x` (valeur détectée, jamais fixée).
- Serveur web : OK / erreur. Planificateur : « dernier passage il y a X s ». Base de données : OK / erreur.
- Découverte réseau : active / inactive. MikroTik : connecté / désactivé / injoignable.
- **Postes en ligne** et **sessions en cours** (lecture via l'API locale).
- Dernière sauvegarde, espace disque restant, version, durée de fonctionnement.
- **Bandeau d'incident** : « Dernier arrêt anormal détecté à HH:MM » lorsqu'une coupure a eu lieu.

**Boutons essentiels**
- **Ouvrir** (ouvre l'interface web dans le navigateur par défaut, sur la bonne adresse) : bouton principal.
- **Démarrer / Arrêter / Redémarrer**, avec confirmation explicite s'il y a des sessions en cours (« 5 sessions payantes sont en cours : elles seront figées »).
- **Copier le code d'installation des postes**.
- **Sauvegarder maintenant**.
- **Réseau** : choix de l'interface et du port avec **test intégré** (« Ce port est libre / occupé par … »).
- **Exporter le diagnostic** (zip : logs, état, versions, sans secrets).
- Case « Lancer avec Windows » (activée par défaut par l'installateur).

### 2.4.3 Comportements
- Instance unique ; reconnexion automatique au pipe si le service redémarre ; état « Service introuvable » lisible (avec bouton Réparer, qui relance l'installateur en mode réparation).
- Icône de la zone de notification colorée (vert/orange/rouge) ; notification toast sur incident.

## 2.5 L'Installateur Tout-en-un : Inno Setup [DÉCISION ACTÉE]

### 2.5.1 Principes
- **Séparation stricte** :
  - `C:\Program Files\CyberManager\` : code, binaires, PHP, Caddy, `vendor/` (lecture seule, remplaçable à chaque mise à jour).
  - `C:\ProgramData\CyberManager\` : **données** (`config\.env`, `config\serveur.json`, `data\cyber.sqlite`, `storage\`, `cache\`, `logs\`, `sauvegardes\`). **Jamais touchées** par une mise à jour ou une désinstallation (sauf case explicite).
  - Pas de profil utilisateur (chemins avec accents, OneDrive, droits variables).
- Installateur **idempotent** : détecte première installation / mise à jour / réparation.
- **Signature de code** recommandée (SmartScreen, antivirus : PHP et un serveur web embarqué sont des cibles de faux positifs).
- Assistant en **français** (anglais optionnel), une seule élévation UAC, mode silencieux (`/VERYSILENT` + paramètres) pour la réinstallation.

### 2.5.2 Étapes de l'assistant
1. **Vérifications préalables** : version de Windows, élévation, espace disque, installation existante, présence d'un ancien XAMPP / d'une ancienne base à importer, ports 80/443 occupés.
2. **Détection réseau** : lister les cartes ; une seule → la prendre ; plusieurs → présélectionner celle qui porte la passerelle par défaut et un réseau privé, liste avec noms lisibles, option « toutes les interfaces ».
3. **Choix du port** : tester le **80** d'abord (URL sans numéro), sinon premier port libre d'une liste ; valider par une **vraie tentative d'écoute** ; enregistrer dans `serveur.json`.
4. **Questions au gérant** (tableau ci-dessous).
5. Copie des fichiers, génération du `.env`, migrations, création du compte administrateur et des réglages initiaux.
6. **Pare-feu** : règles liées au **programme** (Caddy, découverte UDP), profils « tous » (un réseau de cyber est souvent classé « Public »), restreintes au sous-réseau local (`RemoteAddress=LocalSubnet`).
7. **Enregistrement du service** avec récupération automatique après échec ; installation du raccourci de la télécommande (lancement avec Windows).
8. **Auto-test** (voir §2.5.4).
9. **Écran final** : adresse du serveur, **code d'installation des postes** (Copier), bouton « Ouvrir Cyber Manager ».

### 2.5.3 Demandé vs détecté

| Demandé au gérant | Détecté / généré automatiquement |
|---|---|
| Nom du cyber, nom de l'administrateur | Interfaces réseau, adresse IP, port libre |
| **Mot de passe admin initial** (confirmation + indicateur de robustesse) | Fuseau horaire (Windows → IANA : `Indian/Antananarivo`) |
| Langue de l'interface | `APP_KEY`, code d'installation des postes |
| **Dossier de sauvegarde** (suggérer un autre disque ou une clé) | Nombre de cœurs → taille du pool PHP |
| Case « Empêcher la mise en veille / l'hibernation de ce PC » | Nom de la machine, version installée |
| *(MikroTik : ne rien demander ici)* | Configuration MikroTik : **dans l'interface web**, avec bouton « Tester la connexion » |

### 2.5.4 Auto-test (« Terminé » = tout vert)
Démarrage du service ; appel de l'URL de santé ; écriture/lecture en base ; tick du planificateur observé ; réponse à une requête de découverte UDP. En cas d'échec : message humain et bouton « Exporter le diagnostic ». L'écran « Terminé » n'apparaît **qu'en cas de succès**.

### 2.5.5 Désinstallation et mise à jour
- Désinstalleur propre : arrêt du service, suppression des règles pare-feu et du service, suppression de `Program Files`. Les données de `ProgramData` sont **conservées par défaut** (case « Supprimer aussi mes données » avec double confirmation).
- Mise à jour : arrêt du service → **sauvegarde automatique** → remplacement du code → redémarrage (migrations lancées par la séquence de démarrage, §2.3.2).

### 2.5.6 Pièges à anticiper
- Conflit avec Apache/MySQL de XAMPP sur le port 80 : détecter, expliquer, proposer un autre port ou la désactivation de l'ancien service.
- **Mise en veille, hibernation, démarrage rapide** de Windows ; **Windows Update** qui redémarre le serveur en pleine journée → définir les heures d'activité.
- Antivirus : exclusions documentées si nécessaire.
- Réservation DHCP recommandée pour l'adresse du serveur (la découverte UDP compense un changement d'adresse).

---

# 3. SPÉCIFICATION FONCTIONNELLE : LA RÉSILIENCE AUX COUPURES (JIRAMA)

## 3.1 Principe central : le gel rétroactif

Au lieu de décider « après coup » combien déduire, on **fige rétroactivement le chrono de la session au dernier signe de vie connu**.

- Le mécanisme existe déjà : `PosteSessionService::suspendre()` fige le chrono (`etat = en_pause`, `date_heure_suspension`), et `reprendre()` ajoute la durée de pause à `duree_suspension`. `Session::secondesConsommees()` s'arrête déjà à `date_heure_suspension` pour une session `en_pause`.
- **Évolution** : `suspendre()` accepte un instant **passé** :

```php
public function suspendre(
    Poste $poste,
    ?CarbonInterface $a = null,      // instant de gel (par défaut : maintenant)
    string $motif = 'manuel',        // manuel | coupure_poste | coupure_serveur | horloge
    ?int $incidentId = null
): bool
```
  avec `date_heure_suspension = $a` (**borné** : jamais antérieur à `date_heure_debut` ni à `date_heure_reprise`, jamais dans le futur).
- À la reprise, la pause calculée (`now() - date_heure_suspension`) englobe automatiquement toute la période d'obscurité : **le client n'est jamais facturé pour elle**.

> **Règle d'or anti double-déduction** : la déduction `deduireTempsHorsLigne()` n'agit que sur une session `en_cours`. Le gel doit **toujours précéder** le retour en ligne. Une même période ne doit jamais être comptée par les deux mécanismes.

## 3.2 Les deux événements à distinguer

| | **A. Un poste seul tombe** | **B. Le serveur redémarre après un arrêt anormal** |
|---|---|---|
| Cause typique | Coupure locale, plantage, extinction du PC client | Coupure Jirama (serveur inclus), plantage, extinction brutale |
| Détection | Aucun heartbeat depuis plus de `delai_injoignable_secondes` (job `postes:maintenance`) | Au démarrage : drapeau `arret_propre` absent/faux **ou** écart important avec `server_alive_at` |
| Instant de gel | `dernier_heartbeat` du poste | `dernier_heartbeat` du poste (≤ `server_alive_at`) |
| Action | Gel rétroactif + motif `coupure_poste` + coupure Internet du poste | Création d'un **incident**, gel rétroactif de **toutes** les sessions `en_cours`, ouverture de la fenêtre de reconnexion |
| Micro-coupure < délai d'injoignabilité | Inchangé : gérée par `deduireTempsHorsLigne` (aucun changement d'état visible) | — |

### 3.2.1 `server_alive_at` et `arret_propre`
- **`server_alive_at`** : horodatage écrit toutes les **10 s** par une tâche planifiée (`serveur:pulse`) dans `cyber_settings` (clé `serveur.alive_at`), valeur en secondes UTC. Écriture protégée par WAL/`synchronous=FULL`.
- **`arret_propre`** : drapeau écrit par le **service hôte** (fichier `ProgramData\...\etat\arret_propre.flag` + miroir en base). Positionné à `true` lors d'un arrêt contrôlé, remis à `false` au démarrage.
- **Détection au démarrage** (commande `php artisan serveur:demarrage`, appelée par le service **avant** de lancer le web et le planificateur) :
  - `arret_propre = false` **et** `now() - server_alive_at > 30 s` ⇒ **arrêt anormal** ⇒ incident de type `coupure_serveur`.
  - `debut_at = server_alive_at`, `fin_at = now()` (redémarrage du serveur).
- La commande est **idempotente** (un incident par redémarrage, identifié par `server_alive_at`), exécutée dans une transaction.

### 3.2.2 Procédure `serveur:demarrage` (événement B)
1. Contrôle d'horloge (§3.4). Si suspecte → incident `horloge_suspecte`, **aucun gel ni clôture automatique**, bandeau gérant.
2. Créer l'incident `coupure_serveur` (`debut_at`, `fin_at`, durée).
3. Pour **chaque session `en_cours`** (Ethernet) : `suspendre($poste, $a = dernier_heartbeat du poste, 'coupure_serveur', $incident)`, calcul du montant figé, rattachement à l'incident.
4. Pour les **sessions Wi-Fi** : même principe (voir §3.7).
5. Mettre à jour les compteurs de l'incident (`nb_sessions_figees`, `montant_fige_total`).
6. Enregistrer `serveur_demarre_at` (point de départ de la fenêtre de reconnexion).
7. Les postes sont mis à l'état « suspendu » ; le réseau est coupé par la réconciliation MikroTik existante (`reconcilierReseau` coupe déjà tout poste dont la session n'est pas `en_cours`).

## 3.3 Fenêtre de grâce post-redémarrage (remplace les 90 s)

Remplace la garde fixe de 90 s (`postes.garde_redemarrage_secondes`, stockée dans le cache fichier) par une logique **persistée en base** et **configurable** :

- `fenetre_reconnexion_minutes` : **5 à 10 minutes** (défaut **8**), comptées **depuis `serveur_demarre_at`**.
- Pendant la fenêtre : **aucune clôture automatique** pour injoignabilité ; aucune session ne passe en `terminee` du seul fait qu'un poste n'a pas encore reparu (les PC mettent du temps à redémarrer, passer le BIOS, Windows, le Gardien...).
- Passé la fenêtre : les sessions gelées dont le poste **n'a pas reparu** suivent la règle d'abandon (§3.5) ; leur facturation reste **limitée au gel**.
- Si le service redémarre pendant la fenêtre, la fenêtre repart de zéro.

## 3.4 Garde d'horloge

Une coupure peut remettre l'horloge d'un PC à zéro (pile du BIOS faible) ou la désynchroniser avant la synchronisation réseau. Tout le calcul repose sur `now()` **du serveur**.

- Si `now() < server_alive_at`, ou si l'écart dépasse un seuil aberrant (par défaut : > 7 jours, ou année antérieure à celle de la version installée) : état **« horloge à vérifier »**.
  - Aucun gel ni clôture automatique, aucune déduction calculée.
  - Bandeau d'alerte rouge côté gérant avec action « L'heure est correcte, continuer » (journalisée).
- Le service hôte attend la synchronisation Windows (borné) avant d'appeler `serveur:demarrage`.

## 3.5 Politique de reprise et réglages modifiables

### 3.5.1 Cycle de vie de la session (côté base)

```
 en_cours ──(coupure détectée, gel rétroactif)──► en_pause [motif_pause = coupure_*]
    ▲                                                  │
    │                         ┌────────────────────────┼─────────────────────────┐
    │                         ▼                        ▼                         ▼
    │              poste revenu (poste_revenu_at)   poste absent            abandon (délai)
    │                         │                        │                         │
    │   politique « client » : bouton Reprendre   délai « poste absent »    terminee [motif_fin = coupure]
    │   politique « auto »   : reprise immédiate  écoulé → clôture          facturée jusqu'au gel
    │   politique « gérant » : attente du gérant       │
    └───────────(reprendre())───────────┘              └────────────────────────► terminee
```

- États existants conservés (`en_cours`, `en_pause`, `terminee`) : **pas de nouvel état**, un nouveau champ **`motif_pause`** distingue la pause volontaire d'une pause due à une coupure.
- Un forfait en pause **ne peut pas expirer** (`sessionExpiree()` exige `en_cours`).
- À la reprise d'un forfait : recalculer `date_heure_fin_prevue` (**ajouter la durée de pause**), car elle est calculée au démarrage et n'intègre pas les déductions. L'affichage client et admin doit en outre se baser sur `secondesConsommees()`.
- À la reprise d'une pause `coupure_*` : cumuler la durée dans `duree_coupure_secondes` (pour l'affichage « temps non facturé »).

### 3.5.2 Les trois politiques (réglage `politique_reprise`)

| Politique | Comportement | Quand la choisir |
|---|---|---|
| **`client`** (défaut) | La session reste gelée ; l'Écran affiche « Session conservée » avec bouton **Reprendre** | Cas général : le client confirme sa présence, aucune facturation d'absent |
| `gerant` | Gelée jusqu'à décision du gérant dans l'interface web | Gérant qui veut tout contrôler |
| `auto` | Reprise automatique dès le retour du poste | Coupures très fréquentes, clients présents ; **risque** : le chrono tourne si le client est parti |

> **[DÉCISION ACTÉE]** Défaut = `client` : après 45 min, beaucoup de clients sont partis ; le travail en cours est de toute façon perdu à la coupure, donc cliquer « Reprendre » n'est pas une contrainte.

### 3.5.3 Tous les réglages (remplacent les constantes en dur)
Stockés dans `cyber_settings` (clés préfixées `resilience.`), lus via un service dédié `ParametresResilience` (valeurs par défaut, bornes, invalidation du cache de config), éditables dans la page « Résilience » de l'interface web (§3.8).

| Clé | Défaut | Bornes | Remplace |
|---|---|---|---|
| `politique_reprise` | `client` | `client` / `gerant` / `auto` | n/a (nouveau) |
| `delai_injoignable_secondes` | 180 | 60 à 900 | `Poste::DELAI_HORS_LIGNE` |
| `delai_grace_agent_secondes` | 180 | 60 à 900 | `Poste::DELAI_GRACE_AGENT` (transmis aux agents par handshake/heartbeat) |
| `fenetre_reconnexion_minutes` | 8 | 5 à 10 (modifiable jusqu'à 30) | garde de redémarrage de 90 s |
| `abandon_apres_retour_minutes` | 20 | 0 à 240 (0 = jamais) | n/a (nouveau) |
| `abandon_poste_absent_minutes` | 120 | 0 à 1440 (0 = jamais) | `postes.hors_ligne_tolerance_minutes` (comportement adapté) |
| `port_decouverte_udp` | 47474 | 1024 à 65535 | port UDP en dur (Règle 1) |
| `horloge_garde_active` | oui | oui / non | n/a (nouveau) |

- **Abandon** : une session gelée est clôturée (`motif_fin = coupure`) **facturée jusqu'au gel**, Internet reste coupé, selon :
  - `abandon_apres_retour_minutes`, compté à partir de `poste_revenu_at` (**retour du poste**, jamais l'heure de la coupure) ;
  - `abandon_poste_absent_minutes`, compté depuis le gel si le poste n'a jamais reparu.
- Les agents reçoivent leurs délais dans les réponses `handshake`/`heartbeat` (déjà en place : `delai_hors_ligne_s`, `delai_grace_agent_s`).

## 3.6 Comportement de l'Écran client au retour du courant

Contrat API (réponse `handshake` et `heartbeat`, bloc `session`) : ajouter

```json
"session": {
  "etat": "en_pause",
  "interrompue": true,
  "motif_pause": "coupure_serveur",
  "politique_reprise": "client",
  "temps_conserve_s": 1830,
  "restant_s": 1770,
  "montant_fige": 600,
  "peut_reprendre": true
}
```

Écran de verrouillage (aux couleurs du cyber, `branding` existant) :

- **Politique `client`** : « **Coupure de courant : votre session est conservée.** Temps utilisé : 30 min · Restant : 29 min [ **Reprendre** ] ». Le bouton appelle l'endpoint existant `reprendreSession`.
- **Politique `gerant`** : « Votre session est conservée. En attente du gérant. » (pas de bouton).
- **Politique `auto`** : bandeau « Reprise de votre session... » puis déverrouillage.
- **Session abandonnée/clôturée** : « Votre session a été clôturée après la coupure. Montant : X Ar. »
- Le serveur **refuse** (409) la reprise si la session n'est plus reprenable ; le message d'erreur est affiché lisiblement.

## 3.7 Cas particuliers

- **Sessions Wi-Fi** : même principe (gel rétroactif). Un MikroTik qui redémarre perd ses sessions HotSpot : la synchronisation (`WifiSessionService`) doit les traiter comme **interrompues**, pas comme terminées. L'état `etat_wifi` (`deconnecte`/`en_pause`, `deconnecte_at`) existe déjà : l'incident l'utilise avec `deconnecte_at = gel`. *`WifiSessionService` n'était pas dans le contexte fourni : audit obligatoire en étape 1.0.*
- **Deux coupures rapprochées** : un incident par redémarrage ; une session déjà `en_pause` n'est pas regelée (idempotence).
- **Coupure pendant une reprise ou une clôture** : transactions `IMMEDIATE` + invariants vérifiés au prochain démarrage (aucune session `en_cours` sans heartbeat récent).
- **Poste qui redémarre avant le serveur** : l'agent réessaie ; il n'y a pas de clôture tant que la fenêtre de reconnexion n'est pas écoulée.
- **Serveur sur onduleur, PC clients non protégés** : gel déclenché par l'événement A, un par poste ; pas d'incident serveur.

## 3.8 Modifications de la base de données

### 3.8.1 Colonnes ajoutées à `cyber_sessions`

| Colonne | Type | Rôle |
|---|---|---|
| `motif_pause` | string nullable | `manuel`, `coupure_poste`, `coupure_serveur`, `horloge` |
| `incident_id` | foreign key nullable | Rattachement à l'incident |
| `poste_revenu_at` | datetime nullable | Retour du poste après gel (départ du délai d'abandon) |
| `duree_coupure_secondes` | unsigned integer, défaut 0 | Cumul du temps non facturé dû aux coupures (hors `duree_hors_ligne_secondes`) |

*Temps non facturé affiché = `duree_hors_ligne_secondes` + `duree_coupure_secondes`.*

### 3.8.2 Nouvelle table `cyber_incidents`

| Colonne | Type | Rôle |
|---|---|---|
| `id` | pk | |
| `type` | string | `coupure_serveur`, `coupure_poste`, `horloge_suspecte` |
| `debut_at` | datetime | Dernier signe de vie (`server_alive_at` ou `dernier_heartbeat`) |
| `fin_at` | datetime nullable | Redémarrage / retour |
| `duree_secondes` | integer nullable | |
| `poste_id` | foreign key nullable | Pour `coupure_poste` |
| `nb_sessions_figees` | integer | |
| `montant_fige_total` | integer | En Ar |
| `politique_appliquee` | string | Politique en vigueur lors de l'incident |
| `statut` | string | `ouvert` / `traite` |
| `traite_par`, `traite_at` | nullable | Acquittement par le gérant |
| `details` | json nullable | Contexte technique |
| `created_at`, `updated_at` | | |

Contraintes : index sur (`type`, `debut_at`) ; unicité logique (`type`, `debut_at`) pour l'idempotence de `serveur:demarrage`.

### 3.8.3 Clés `cyber_settings`
Voir §3.5.3, plus `serveur.alive_at`, `serveur.arret_propre`, `serveur.demarre_at`.

## 3.9 Interface web pour le gérant

- **Bandeau d'incident** (toutes les pages admin, jusqu'à acquittement) : « Coupure détectée : serveur indisponible de HH:MM à HH:MM (≈ 45 min). 5 sessions interrompues. [Voir le détail] ».
- **Page « Incidents »** : liste des incidents, détail avec **les sessions concernées** (poste, temps conservé, montant figé, statut) et actions **Reprendre** / **Terminer (facturé jusqu'à HH:MM)** ; **actions groupées** (« Reprendre tout », « Terminer tout »).
- **Détail d'une session** : ligne explicite « **Temps non facturé : coupure 45 min** » (traçabilité financière) ; statut « Interrompue (coupure) ».
- **Tableau des postes** : badge « Session conservée » distinct d'une suspension manuelle (`motif_pause`).
- **Journal des incidents** (début, fin, postes touchés, décisions prises, par qui).
- **Page « Résilience »** (réglages §3.5.3) avec explications en langage simple et bornes validées.
- **Checklist de résilience** (aide intégrée) : onduleur pour le serveur **et** le MikroTik (meilleur investissement) ; option BIOS « Restore on AC Power Loss = Power On » ; connexion Windows automatique sur les postes ; réservation DHCP du serveur ; sauvegarde sur un autre disque.

## 3.10 Matrice de tests de résilience (à valider avant livraison)

| # | Scénario | Attendu |
|---|---|---|
| T1 | Serveur + 5 PC coupés 45 min ; PC redémarrés **avant** le serveur | 5 sessions gelées au dernier heartbeat, aucune clôture, incident créé |
| T2 | Idem, PC redémarrés **après** le serveur (15 min plus tard) | Idem ; reprise selon politique ; aucune clôture dans la fenêtre |
| T3 | Un PC seul coupé, serveur actif | Gel rétroactif au dernier heartbeat après `delai_injoignable`, Internet coupé |
| T4 | Micro-coupure d'un PC (< délai) | Déduction `duree_hors_ligne_secondes`, aucun changement d'état visible |
| T5 | Deux coupures rapprochées du serveur | Deux incidents, aucune double déduction |
| T6 | Coupure pendant une reprise ou une clôture | Aucune session incohérente ; invariants vérifiés au démarrage |
| T7 | Horloge incorrecte au redémarrage | Aucun calcul ni clôture ; bandeau « horloge à vérifier » |
| T8 | Arrêt brutal pendant une écriture en base | SQLite reprend (WAL) ; `quick_check` OK ou restauration proposée |
| T9 | Forfait repris après coupure | `date_heure_fin_prevue` recalculée, expiration correcte |
| T10 | Politiques `client` / `gerant` / `auto` | Comportement conforme à §3.5.2 |

**Invariant à vérifier pour chaque test** : `montant facturé = f(temps consommé avant la coupure)`, ni plus, ni moins.

---

# 4. DÉCOUPAGE DU TRAVAIL EN ÉTAPES PROGRESSIVES (ROADMAP)

Ordre : du plus immédiat (à la maison, sur le code Laravel existant) au packaging final. **Chaque étape se valide avant la suivante.**

| Étape | Contenu | Dépend de | Où ça se fait |
|---|---|---|---|
| **1** | Laravel : résilience (gel rétroactif, incidents, réglages, interface) + optimisation SQLite | rien | Le PC de développement |
| **2** | Moteur d'exécution : bundle Caddy + PHP portable + génération de configuration | Étape 1 | Poste de dev (+ une VM Windows propre) |
| **3** | Application C# : service hôte + télécommande | Étape 2 | Poste de dev / VM |
| **4** | Installateur final Inno Setup | Étapes 1 à 3 | VM Windows neuve + PC réel |

Les étapes 1 et 2 peuvent se tester **sans** C#, via un script de développement qui lance le bundle à la main (jamais livré au gérant).

---

# 5. SPÉCIFICATIONS TECHNIQUES PAR ÉTAPE (DÉFINITION DU « TERMINÉ »)

## ÉTAPE 1 : Côté Laravel : résilience et SQLite (priorité immédiate)

### 1.0 Prérequis : audit de compatibilité SQLite
- Exécuter `migrate:fresh` sur SQLite ; corriger les migrations incompatibles (modifications de colonne, `enum`, `dropColumn`).
- Lire `WifiSessionService`, `AccesInternetService`, `SessionExpirationService`, `TarifService`, `IdentiteMaterielle` (non fournis dans le contexte actuel) pour vérifier les interactions avec le gel et l'idempotence.
- Lister toutes les constantes/valeurs en dur (`DELAI_*`, port UDP, `config/postes.php`).

### 1.1 Optimisation SQLite
- **Modifier** `config/database.php` (§2.2.4 : WAL, IMMEDIATE, `busy_timeout`, `synchronous=FULL`).
- **Créer** `app/Console/Commands/SauvegarderBase.php` (`serveur:sauvegarder` : `VACUUM INTO`, rotation).
- **Créer** `app/Console/Commands/ImporterMysql.php` (`serveur:importer-mysql`) pour migrer une ancienne base XAMPP.

### 1.2 Base de données
- **Créer** les migrations : colonnes de `cyber_sessions` (§3.8.1), table `cyber_incidents` (§3.8.2).
- **Créer** `app/Models/Incident.php` (casts, relations `sessions()`, `poste()`).
- **Modifier** `app/Models/Session.php` (`fillable`, casts, relation `incident()`, accesseur « temps non facturé »).

### 1.3 Réglages et services
- **Créer** `app/Services/ParametresResilience.php` (lecture/écriture bornée des clés `resilience.*`, repli sur `config/postes.php`).
- **Modifier** `app/Models/Poste.php` : `DELAI_HORS_LIGNE` et `DELAI_GRACE_AGENT` ne sont plus des constantes absolues mais lus via `ParametresResilience` (conserver les constantes comme valeurs par défaut).
- **Créer** `app/Services/ResilienceService.php` : `figerSession()`, `traiterDemarrageServeur()`, `traiterRetourPoste()`, `appliquerPolitique()`, `abandonnerSiNecessaire()`, `verifierHorloge()`.

### 1.4 Logique métier existante
- **Modifier** `app/Services/PosteSessionService.php` :
  - `suspendre()` accepte `?CarbonInterface $a`, `$motif`, `$incidentId` (borné, idempotent) ;
  - `reprendre()` recalcule `date_heure_fin_prevue` pour un forfait, cumule `duree_coupure_secondes` si `motif_pause` est une coupure ;
  - `cloturerSiInjoignable()` devient `figerSiInjoignable()` (gel rétroactif + coupure réseau) puis `abandonnerSiNecessaire()` pour la clôture facturée au gel ;
  - `traiterRetourEnLigne()` : si la session est `en_pause` avec motif coupure, renseigner `poste_revenu_at` et appliquer la politique (`auto` → `reprendre()` + `connecterReseau()`).
- **Modifier** `app/Console/Commands/MaintenancePostes.php` : remplacer la garde de 90 s en cache fichier par la fenêtre persistée en base ; gel (événement A) ; abandon selon §3.5.3.
- **Créer** `app/Console/Commands/ServeurPulse.php` (`serveur:pulse`, écrit `serveur.alive_at`) et `app/Console/Commands/ServeurDemarrage.php` (`serveur:demarrage`, §3.2.2).
- **Modifier** `routes/console.php` : planifier `serveur:pulse` toutes les 10 s ; conserver les cadences existantes.
- **Modifier** `app/Http/Controllers/Api/AgentController.php` :
  - **retirer** `reconcilierTous()` du chemin du heartbeat ;
  - enrichir `resumeSession()` (bloc `interrompue`, `motif_pause`, `politique_reprise`, `temps_conserve_s`, `peut_reprendre`, `montant_fige`) ;
  - gérer le retour après gel dans `handshake` et `heartbeat` ;
  - autoriser `reprendreSession` pour une session gelée (selon la politique).
- Ajouter une route de santé `GET /api/sante` (base + dernier tick).

### 1.5 Interface web
- **Créer** `IncidentController` (liste, détail, actions Reprendre/Terminer unitaires et groupées, acquittement) ; vues `incidents/index` et `incidents/show`.
- **Créer** `ParametresResilienceController` et la vue de réglages (§3.5.3, §3.9) ; composant **bandeau d'incident** dans le layout admin.
- **Modifier** les vues de session et de postes (« temps non facturé », badge « Session conservée »).

### 1.6 Tests automatisés
- Tests PHPUnit/Feature avec `Carbon::setTestNow()`, sur SQLite : scénarios T1 à T10 (§3.10), idempotence de `serveur:demarrage`, bornes de `suspendre()`.

### 1.7 Patch minimal de l'Écran client (dépôt `cyber-manager-agent`)
- Afficher le message « Session conservée » et le bouton **Reprendre** selon le bloc `session.interrompue` (§3.6). Aucune autre modification du client.

### DoD de l'étape 1 (à valider intégralement)
- [ ] `migrate:fresh` et la suite de tests passent **sur SQLite** (WAL, IMMEDIATE actifs, vérifié par `PRAGMA journal_mode`).
- [ ] Tests T1 à T10 verts ; invariant financier vérifié ; aucune double déduction.
- [ ] **Simulation réelle** : 3 postes en session, arrêt brutal du processus PHP/serveur (kill), redémarrage 10 minutes plus tard → incident créé, sessions gelées, montants corrects, bandeau visible, reprise via le bouton client.
- [ ] Plus aucune constante de délai ni port en dur : tout vient de `ParametresResilience`.
- [ ] `heartbeat` ne fait plus d'appel MikroTik synchrone ; temps de réponse stable avec MikroTik **désactivé** et avec MikroTik **injoignable**.
- [ ] `serveur:sauvegarder` produit une sauvegarde cohérente (réouverture + `quick_check` OK) ; `serveur:importer-mysql` migre une base XAMPP de test.
- [ ] Les 3 politiques de reprise testées de bout en bout.
- [ ] `IMPLEMENTED.md` de l'étape rédigé, Annexe D mise à jour.

---

## ÉTAPE 2 : Le moteur d'exécution autonome (bundle Caddy + PHP)

### Fichiers à créer
- **Structure du bundle** (dépôt `cyber-manager-bundle` ou dossier `build/`) :
  ```
  bundle/
    app/                 (copie de l'application, vendor/ prod, assets compilés)
    runtime/php/         (php.exe, php-cgi.exe, extensions, php.ini.template)
    runtime/caddy/       (caddy.exe, Caddyfile.template)
    tools/               (outils internes éventuels)
  ```
- `build/construire-bundle.ps1` : script de **développement** (jamais livré au gérant) qui assemble le bundle (`composer install --no-dev`, `npm run build`, copie, vérification des extensions PHP).
- **Commande artisan `serveur:preparer`** (source unique de vérité, testable en PHP) :
  - lit `serveur.json` (interface choisie, port souhaité, port UDP, taille du pool) ;
  - **liste les interfaces** (`net_get_interfaces()`), choisit selon la règle §2.5.2 ;
  - **choisit les ports** (80 puis liste de repli ; ports FastCGI internes libres sur `127.0.0.1`) en testant l'écoute réelle ;
  - génère `Caddyfile`, `php.ini` et `runtime.json` (ports effectifs, URL(s), PID attendus) dans `ProgramData\...\runtime\` ;
  - retourne un **code de sortie** et un JSON de résultat lisible par le service C#.
- **Commande `cyber:decouverte`** (existante) : port UDP lu depuis le réglage (Règle 1), réponse avec l'adresse de l'interface réceptrice.
- `public/index.php`/`bootstrap/app.php` : prise en charge des variables d'environnement de chemin (§2.2.3).
- `scripts/dev/lancer-bundle.ps1` (développement) : lance Caddy + pool php-cgi + `schedule:work` à la main pour tester le bundle **avant** le service C#.

### Logique et comportement attendu
- Le bundle démarre **sans XAMPP, sans Composer, sans Node**, depuis un dossier quelconque.
- Application fonctionnelle en lecture seule dans un dossier de code, données dans un dossier séparé.
- Un pool de N `php-cgi.exe` répartit la charge ; les ports internes ne sont jamais fixés à l'avance.
- Les fichiers `/storage/*` sont servis sans lien symbolique.

### DoD de l'étape 2
- [ ] Sur une **VM Windows neuve** (aucun PHP/XAMPP/Composer installé), le bundle démarre et l'interface web est accessible depuis un **autre PC du réseau**.
- [ ] Application OK depuis un dossier **en lecture seule** (écriture uniquement dans le dossier de données).
- [ ] **Test de charge** : 20 postes simulés, heartbeats à la cadence réelle + une page admin ouverte → pas de blocage, latence p95 acceptable (cible < 300 ms) ; un MikroTik injoignable ne ralentit pas les heartbeats.
- [ ] `serveur:preparer` testé : port 80 libre, port 80 occupé (repli), deux cartes réseau, aucune carte « privée », chemins avec **espaces et accents**.
- [ ] Découverte UDP : un poste retrouve le serveur avec **deux cartes** (la bonne adresse est renvoyée).
- [ ] `schedule:work` exécute toutes les tâches aux cadences prévues (5 s, 10 s, 30 s) sans fenêtre visible.
- [ ] Taille du bundle et temps de démarrage mesurés et documentés.
- [ ] `IMPLEMENTED.md` de l'étape rédigé, Annexe D mise à jour.

---

## ÉTAPE 3 : L'application C# : service hôte et télécommande

### Fichiers à créer (solution `CyberManager.Server`)
```
CyberManager.Server.sln
  CyberManager.Server.Common/       (contrats : messages du pipe, chemins, modèles de configuration)
  CyberManager.Server.Host/         (Worker Service : CyberManagerServerService)
      Program.cs
      SupervisorWorker.cs           (cycle de vie, séquence de démarrage §2.3.2)
      ChildProcessManager.cs        (lancement sans fenêtre, Job Object, redémarrage avec temporisation)
      HealthMonitor.cs              (santé HTTP, dernier tick du planificateur)
      PipeServer.cs                 (Named Pipe, ACL, protocole JSON v1)
      ClockGuard.cs                 (attente de synchronisation d'heure)
      DatabaseGuard.cs              (quick_check, restauration)
      BackupScheduler.cs
  CyberManager.Server.Control/      (Télécommande WinForms/WPF : CyberManagerServer.exe)
      MainForm.cs, TrayIcon.cs, PipeClient.cs, QrCodeView.cs, NetworkSettingsDialog.cs, DiagnosticExporter.cs
```

### Logique et comportement attendu
- Le service exécute la séquence de démarrage §2.3.2 dans l'ordre, expose son état par le pipe et supervise tous les enfants (§2.3.3).
- La télécommande affiche **exactement** les indicateurs et boutons du §2.4.2 ; fermer la télécommande n'arrête rien.
- Messages du pipe (version 1) : `GetStatus`, `Start`, `Stop`, `Restart`, `BackupNow`, `SetNetwork`, `TestNetwork`, `ExportDiagnostic`, `GetInstallCode`. Les arrêts avec sessions en cours exigent une confirmation explicite.
- États exposés : `DEMARRAGE`, `PRET`, `DEGRADE`, `ARRETE`, `EN_PANNE`, `BASE_A_RESTAURER`, `HORLOGE_A_VERIFIER`.
- Aucune fenêtre console ne s'ouvre jamais.

### Pièges à traiter
- Le service ne peut afficher aucune interface (session 0) : tout passe par la télécommande.
- Chemins avec espaces, noms d'utilisateur accentués, antivirus (quarantaine de PHP/Caddy) : tests dédiés.
- Pas de PHP orphelin : tuer le service doit arrêter tous les enfants (Job Object).

### DoD de l'étape 3
- [ ] Le service démarre **au boot sans session ouverte** ; après `Restart-Computer`, le serveur répond **avant** toute connexion Windows.
- [ ] **Kill brutal** d'un enfant (Caddy, `php-cgi`, `schedule:work`) : redémarrage automatique avec temporisation ; après N échecs, état `EN_PANNE` lisible.
- [ ] **Kill brutal du service** : aucun processus enfant ne survit.
- [ ] **Coupure simulée** (arrêt forcé de la VM) pendant des sessions de test → au redémarrage, `serveur:demarrage` a créé l'incident et figé les sessions (lien avec l'étape 1).
- [ ] La télécommande reflète en moins de 5 s un changement d'état ; fermer/rouvrir la télécommande n'a aucun effet sur le serveur.
- [ ] Changement d'interface/port depuis la télécommande : test intégré, redémarrage propre, adresse mise à jour.
- [ ] Export de diagnostic : zip sans mot de passe, token ni `APP_KEY`.
- [ ] Mesure : un démarrage complet à froid prête en **moins de 60 s** (marge sur les 180 s de grâce agent).
- [ ] `IMPLEMENTED.md` de l'étape rédigé, Annexe D mise à jour.

---

## ÉTAPE 4 : L'installateur final (Inno Setup)

### Fichiers à créer
- `installer/CyberManagerServerSetup.iss` : script Inno Setup (assistant en français, pages personnalisées : réseau, port, nom du cyber, administrateur, mot de passe, sauvegarde, mise en veille).
- `installer/Detection.pas` (ou DLL/outil C# appelé par l'installateur) : détection réseau, test de ports, détection XAMPP/ancienne base.
- `installer/post-install.ps1` ou outil C# : création des dossiers `ProgramData`, génération du `.env`, `serveur.json`, droits NTFS, règles pare-feu par programme, enregistrement du service, premier `migrate`, création de l'administrateur.
- `installer/AutoTest.exe` (ou option du service) : auto-test §2.5.4.
- Désinstalleur : arrêt/suppression du service, des règles pare-feu, conservation des données par défaut.
- Pipeline de build : `build/construire-installateur.ps1` (bundle → signature → `.exe` final).

### Logique et comportement attendu
- Installation **en un double-clic** : toutes les décisions techniques sont automatiques ou présentées en langage simple.
- Réinstallation / mise à jour **sans perte de données** (sauvegarde automatique avant remplacement).
- Mode silencieux documenté pour un déploiement sans interaction.
- Écran final : URL, code d'installation des postes (Copier), bouton « Ouvrir Cyber Manager ».

### DoD de l'étape 4
- [ ] **VM Windows neuve** : installation complète sans ouvrir de terminal ; auto-test vert ; un poste client (installé avec `CyberManagerInstall.exe`) se connecte et ouvre une session.
- [ ] **PC avec plusieurs cartes réseau** : bonne interface choisie, découverte correcte.
- [ ] **PC avec XAMPP déjà installé** (port 80 occupé) : conflit détecté, solution proposée, import de l'ancienne base réussi.
- [ ] **Réinstallation par-dessus** et **mise à jour** : aucune perte de données, sauvegarde créée, migrations jouées.
- [ ] **Désinstallation** : plus aucun processus, service, règle pare-feu ni dossier de code ; données conservées par défaut.
- [ ] Test sur un **PC réel du cybercafé** : démarrage avec Windows, coupure de courant réelle ou simulée, reprise des sessions conforme au §3.
- [ ] Installateur **signé**, testé avec l'antivirus intégré de Windows actif.
- [ ] Documentation d'exploitation courte pour le gérant (une page, captures) et note de support (diagnostic).
- [ ] `IMPLEMENTED.md` final rédigé, Annexe D complétée ; version taguée.

---

# ANNEXES

## Annexe A : État de l'existant (référence)

| Élément | Constat |
|---|---|
| `PosteSessionService` | Cycle de vie complet (création, suspension, reprise, clôture, expiration), déduction du temps hors ligne, mode dégradé MikroTik, `reconcilierReseau()` |
| `Session::secondesConsommees()` | Déjà compatible avec le gel (utilise `date_heure_suspension` pour `en_pause`) |
| `MaintenancePostes` | Verrou en cache fichier ; garde de redémarrage de 90 s (cache, non persistée) ; clôture après tolérance (10 min par défaut) |
| `AgentController` | `handshake`, `heartbeat`, `activer`, `suspendreSession`, `reprendreSession`, `fermerSession`, `commandes` ; `heartbeat` appelle `reconcilierTous()` |
| `routes/console.php` | `postes:maintenance` toutes les 10 s, `--reseau` toutes les 30 s, `wifi:sync-sessions` toutes les 5 s, `sessions:expire` à la minute |
| `config/database.php` | SQLite par défaut avec `busy_timeout`, `journal_mode`, `synchronous` à `null`, `transaction_mode = DEFERRED` |
| Scripts | `preparer-serveur.ps1` (XAMPP, services, sauvegarde, pare-feu), `installer-decouverte.ps1` (tâche UDP 47474) : à remplacer par l'installateur |
| Poste | Constantes `DELAI_HORS_LIGNE = 180`, `DELAI_GRACE_AGENT = 180`, `DELAI_CONFLIT = 12` |

## Annexe B : Glossaire

- **Gardien** : service Windows client qui supervise l'Écran.
- **Écran / Agent** : application plein écran de verrouillage et de session (WinForms + WebView2).
- **Heartbeat** : signal périodique d'un poste vers le serveur.
- **Gel rétroactif** : fixer le chrono d'une session à un instant **passé** (dernier signe de vie).
- **Incident** : enregistrement d'une coupure (serveur ou poste) avec ses conséquences.
- **Fenêtre de reconnexion** : période après redémarrage du serveur où aucune clôture automatique n'est faite.
- **Télécommande** : fenêtre de contrôle du serveur (type Mikhmon Server).

## Annexe C : Décisions ouvertes [À CONFIRMER]

1. Installateur serveur **séparé** (Inno Setup, défaut) ou intégré plus tard au `CyberManagerInstall.exe` (choix serveur/poste) : défaut = séparé.
2. Télécommande en **WinForms** (cohérence avec l'Écran) ou **WPF** : défaut = WinForms.
3. HTTPS local : **non** pour la première version (réseau local fermé) ; à étudier ensuite.
4. Bornes exactes de la fenêtre de reconnexion (5 à 10 min conseillées, défaut 8).
5. Langues de l'interface au-delà du français.

## Annexe D : Suivi d'avancement (à tenir à jour)

| Étape | Sous-lot | Statut | Date | Commentaire |
|---|---|---|---|---|
| 1 | 1.0 Audit SQLite | À faire | | |
| 1 | 1.1 Optimisation SQLite + sauvegarde + import | À faire | | |
| 1 | 1.2 Migrations / modèles | À faire | | |
| 1 | 1.3 Réglages + `ResilienceService` | À faire | | |
| 1 | 1.4 Logique métier + commandes + `AgentController` | À faire | | |
| 1 | 1.5 Interface web | À faire | | |
| 1 | 1.6 Tests T1 à T10 | À faire | | |
| 1 | 1.7 Patch Écran client | À faire | | |
| 2 | Bundle + `serveur:preparer` + tests de charge | À faire | | |
| 3 | Service hôte | À faire | | |
| 3 | Télécommande | À faire | | |
| 4 | Installateur Inno Setup + auto-test + désinstalleur | À faire | | |

---

*Fin du cahier des charges. Prochaine action recommandée : démarrer l'étape 1.0 (audit de compatibilité SQLite), puis 1.1 et 1.2, avant de modifier `PosteSessionService` et `MaintenancePostes`.*