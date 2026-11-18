# CAHIER DES CHARGES — CYBER MANAGER

**Document de référence permanent**
**Version 1.0 — 17 novembre 2026**
**Projet : Cyber Manager (clone de Cybera pour le cyber IZARA à Madagascar)**

---

## AVERTISSEMENT

Ce document est un **catalogue de spécifications**, pas un tableau de bord d'avancement.

Il ne contient **aucune mention de "fait" ou "pas fait"**. Il décrit **tous les lots** et **toutes les étapes** du projet, avec pour chacun :
- le problème à résoudre,
- le comportement attendu,
- les fichiers concernés,
- les dépendances,
- les tests de validation,
- les pièges à éviter.

**Celui qui reprend le travail** (une IA ou un développeur) doit :
1. Lire ce document en entier.
2. Examiner l'état actuel du code (les 5 dossiers).
3. Décider lui-même quelle étape est la prochaine à traiter.
4. Suivre la méthode de travail décrite en Partie 3.

Ce document est **intemporel** : il reste valable que le projet soit à l'étape 1 ou à l'étape 100.

---

## PARTIE 1 — CONTEXTE PERMANENT

### 1.1 Architecture du projet

Le projet est composé de **5 dossiers majeurs**, tous situés dans `E:\xampp\htdocs\` :

| Dossier | Rôle | Technologie |
|---------|------|-------------|
| `cyber-manager` | Serveur (interface web, API, base de données) | Laravel 12, XAMPP, MySQL, Windows |
| `cyber-manager-agent` | Agent client (widget, écran de verrouillage) | C# .NET 10, WinForms + WebView2 |
| `cyber-manager-commun` | Modèles et protocoles partagés | C# .NET 10 |
| `cyber-manager-gardien` | Service Windows (surveillance, lancement de l'écran) | C# .NET 10, Windows Service |
| `cyber-manager-installateur` | Programme d'installation du poste client | C# .NET Framework 4.8 |

**Communication** : API HTTP entre l'agent et le serveur, sur le réseau local.

### 1.2 Règles absolues (non négociables, dans TOUS les cas)

**Règle 1 — Indépendance matérielle**
- Aucune adresse IP fixe, aucun nom de machine, aucun mot de passe en dur dans le code ou les migrations.
- Toute adresse doit être détectée dynamiquement ou configurée par le gérant depuis l'interface.
- Le code doit fonctionner si on change de PC serveur, d'adresse IP, de routeur, ou de réseau.
- Ne dépendre d'aucune marque spécifique (MikroTik ou autre).

**Règle 2 — Simplicité pour le gérant**
- Tout doit se faire depuis l'interface web ou l'installateur.
- Zéro terminal, zéro Winbox, zéro commande PowerShell.
- Le gérant ne doit jamais avoir à chercher un fichier dans un dossier qu'il ne connaît pas.

**Règle 3 — Fonctionner dans tous les cas**
- PC neufs (aucun compte configuré).
- PC déjà configurés (compte admin + compte client existants).
- PC clonés (même identité, disque copié).
- PC avec plusieurs cartes réseau, plusieurs sessions Windows, plusieurs utilisateurs.

**Règle 4 — Production réelle**
- Chaque solution doit être utilisable seule par le gérant, sans le développeur.
- Pas de "solution de test" qui serait remplacée plus tard. Ce qui est livré doit être la version finale.

### 1.3 Structure des 5 dossiers

#### cyber-manager (Laravel)

Contient :
- **Contrôleurs** : `app/Http/Controllers/` (Api/, Auth/, et racine)
- **Modèles** : `app/Models/`
- **Services** : `app/Services/`
- **Middleware** : `app/Http/Middleware/`
- **Migrations** : `database/migrations/`
- **Vues** : `resources/views/`
- **Routes** : `routes/web.php`, `routes/auth.php`, `routes/agent_maj.php`, `routes/console.php`
- **Configuration** : `config/`, `bootstrap/app.php`, `.env`

#### cyber-manager-agent (C#)

Fichiers source principaux :
- `AgentConfig.cs`, `ApiClient.cs`, `ChatPanel.cs`, `ClientWindow.cs`, `LockScreenHtml.cs`
- `Program.cs`, `SessionWidget.cs`, `SurveillanceLiaison.cs`, `ToastForm.cs`, `VoyantServeur.cs`
- `appsettings.json`, `appsettings.Development.json`
- `cyber-manager-agent.csproj`

#### cyber-manager-commun (C#)

Fichiers source :
- `Modeles.cs`, `Protocole.cs`
- `fabriquer.ps1` (script de fabrication des distributions)
- `dist/` (livrables : `ecran-2.0.0.zip`, `gardien.zip`, `ecran/`, `gardien/`, `installateur/`)

#### cyber-manager-gardien (C#)

Fichiers source :
- `CanalEcran.cs`, `CompteWindows.cs`, `CompteWorker.cs`, `Decouverte.cs`, `DecouverteWorker.cs`
- `EmpreinteMaterielle.cs`, `GardienConfig.cs`, `GardienWorker.cs`, `LanceurEcran.cs`
- `MiseAJour.cs`, `Program.cs`, `RestrictionsWindows.cs`, `RestrictionsWorker.cs`, `ServeurClient.cs`

#### cyber-manager-installateur (C#)

Fichiers source :
- `Program.cs`, `Installateur.csproj`, `app.manifest`

---

## PARTIE 2 — CATALOGUE DES LOTS

### Vue d'ensemble des 5 lots

| Lot | Contenu | Thème |
|-----|---------|-------|
| LOT 1 | Problèmes 1, 2, 7, 8, 9, 10 | Sécurité + HotSpot + perte de communication |
| LOT 2 | Problèmes 3, 4, 5, 6 | Compte Windows + identité |
| LOT 3 | Étapes 1 à 12 | Interface serveur (améliorations) |
| LOT 4 | Problèmes 15, 16 | Installateur + désinstalleur |
| LOT 5 | Problème 11 + vérifications | Voyant réseau + finitions |

---

## LOT 1 — SÉCURITÉ + HOTSPOT + PERTE DE COMMUNICATION

### Élément 1.1 — HotSpot par poste

**Problème**
Un poste câblé n'a pas Internet tant qu'on ne saisit pas d'identifiants HotSpot à la main. La route `POST /postes/{poste}/hotspot` pointe vers une méthode qui n'existe plus. Le champ "HotSpot Non configuré" dans `/postes` est inutile.

**Comportement attendu**
Un poste câblé doit avoir Internet automatiquement, sans identifiant, quel que soit le routeur (MikroTik ou autre), sans dépendance matérielle. Le contrôle d'accès Internet doit fonctionner pour le poste et être révocable par le gérant.

**Fichiers concernés**
- `app/Services/MikroTikService.php` (ou équivalent)
- `app/Services/PosteService.php`
- `app/Http/Controllers/PosteController.php`
- `resources/views/postes/index.blade.php`
- `routes/web.php` (route `postes.hotspot` à supprimer ou remplacer)
- Un nouveau service ou une nouvelle méthode pour la gestion de l'accès Internet (nom à décider par l'IA)

**Dépendances**
Aucune.

**Tests de validation**
- Un poste câblé sans identifiant HotSpot a Internet dès qu'il est activé.
- Le gérant peut couper Internet d'un poste depuis l'interface web.
- Le gérant peut rétablir Internet d'un poste depuis l'interface web.
- Le badge dans `/postes` affiche "Internet : actif / coupé / non contrôlé" (et non plus "HotSpot Non configuré").
- Aucune valeur en dur (IP, mot de passe, nom de machine).

**Pièges à éviter**
- Ne pas créer un identifiant HotSpot par poste (viole la règle 1).
- Ne pas dépendre de MikroTik uniquement (règle 1).
- Ne pas laisser la route `postes.hotspot` pointer vers une méthode inexistante.

---

### Élément 1.2 — Perte de communication agent ↔ serveur

**Problème**
Quand l'agent perd le serveur (câble débranché, Wi-Fi coupé), le widget continue de tourner (chrono, montant). Le serveur voit "Non présent" après 30 secondes, clôture après 10 minutes. Le client utilise le poste sans que personne ne le sache.

**Comportement attendu**
Mode dégradé en 3 phases :
- Moins de 30 secondes sans serveur : rien (micro-coupure).
- Entre 30 secondes et 3 minutes : bandeau orange "Liaison perdue", chrono en pause.
- Après 3 minutes : verrouillage "Liaison perdue, contactez l'accueil". Session non fermée, reprise au retour.

Le délai de grâce côté agent (3 minutes) doit être inférieur à la tolérance serveur (10 minutes). Distinguer "câble débranché" (état carte réseau) de "serveur injoignable" (ping). Paramétrable par le gérant.

**Fichiers concernés**
- `cyber-manager-agent/SurveillanceLiaison.cs`
- `cyber-manager-agent/VoyantServeur.cs`
- `cyber-manager-agent/ClientWindow.cs`
- `cyber-manager-agent/SessionWidget.cs`
- `cyber-manager-agent/ApiClient.cs`
- `cyber-manager-gardien/GardienWorker.cs` (si le heartbeat passe par le Gardien)
- `app/Http/Controllers/Api/AgentController.php` (côté serveur, tolérance 10 minutes)

**Dépendances**
Aucune (peut être fait indépendamment).

**Tests de validation**
- Câble débranché : après 30 secondes, bandeau orange ; après 3 minutes, verrouillage.
- Câble rebranché : reprise automatique, session continue.
- Serveur arrêté : même comportement.
- Le chrono ne continue pas à tourner en mode verrouillé.
- Le serveur ne clôture pas la session avant 10 minutes.

**Pièges à éviter**
- Ne pas verrouiller immédiatement (trop brutal).
- Ne pas laisser le chrono tourner sans serveur.
- Ne pas clôturer la session côté serveur avant le délai de grâce agent.

---

### Élément 1.3 — Identifiants en dur (sécurité)

**Problème**
La migration `add_reseau_config_to_cyber_settings` insère un mot de passe routeur et une IP en dur. C'est contraire à la règle 1.

**Comportement attendu**
Retirer ces valeurs. Permettre au gérant de configurer le routeur depuis l'interface, sans valeur par défaut, sans présumer d'une marque ou d'une IP.

**Fichiers concernés**
- `database/migrations/2026_11_18_000000_add_reseau_config_to_cyber_settings.php` (ou migration équivalente)
- Une nouvelle vue ou un nouvel assistant dans l'interface Paramètres (nom à décider)

**Dépendances**
Aucune.

**Tests de validation**
- Après migration, aucune valeur `RootAdmin` ou `128.0.1.1` en base.
- L'interface propose un formulaire vide pour configurer le routeur.
- Aucune valeur par défaut n'est insérée.

**Pièges à éviter**
- Ne pas mettre une autre valeur en dur à la place.
- Ne pas présumer du type de routeur.

---

### Élément 1.4 — Logs qui fuient (sécurité)

**Problème**
`CodeInstallation::verifier()` écrit le code attendu et le code reçu dans `laravel.log`.

**Comportement attendu**
Retirer ces logs sans casser la vérification du code d'installation.

**Fichiers concernés**
- `app/Services/CodeInstallation.php`

**Dépendances**
Aucune.

**Tests de validation**
- Le code d'installation fonctionne toujours (vérification OK / KO).
- Aucune ligne `Log::debug` ou `Log::info` ne contient le code attendu ou le code reçu.
- `laravel.log` ne contient plus ces informations.

**Pièges à éviter**
- Ne pas supprimer la vérification elle-même.
- Ne pas logger le code sous une autre forme.

---

### Élément 1.5 — .env de test (sécurité)

**Problème**
`APP_DEBUG=true`, `APP_ENV=local`, MySQL root sans mot de passe.

**Comportement attendu**
Documenter et permettre une configuration production, sans imposer de méthode. Le gérant doit pouvoir passer en production sans terminal.

**Fichiers concernés**
- `.env`
- Un fichier de documentation (par exemple `README_PRODUCTION.md` ou une section dans l'interface Paramètres)

**Dépendances**
Aucune.

**Tests de validation**
- Un fichier `.env.production` documenté existe.
- L'interface explique comment passer en production.
- Le gérant peut suivre la procédure sans terminal.

**Pièges à éviter**
- Ne pas changer `.env` maintenant si cela gêne le débogage.
- Ne pas imposer une méthode unique (le gérant doit pouvoir choisir).

---

### Élément 1.6 — Textes périmés

**Problème**
La page "Connecter un nouveau poste" dit "en attendant l'installateur" alors que l'installateur existe.

**Comportement attendu**
Mettre à jour le texte pour renvoyer vers l'installation.

**Fichiers concernés**
- `resources/views/enregistrement/_code.blade.php` (ou équivalent)

**Dépendances**
Aucune.

**Tests de validation**
- Le texte ne dit plus "en attendant l'installateur".
- Le texte renvoie vers la page Installation.

**Pièges à éviter**
- Ne pas casser les liens existants.

---

## LOT 2 — COMPTE WINDOWS + IDENTITÉ

### Élément 2.1 — Admin enfermé dehors

**Problème**
`LanceurEcran` lance l'Écran dans la session console active, quel que soit l'utilisateur. Si le gérant ouvre une session admin, l'Écran le recouvre et le Gardien le relance. Le gérant ne peut plus faire de maintenance.

**Comportement attendu**
L'Écran ne verrouille que les comptes standard. Les comptes administrateur sont exemptés.

**Fichiers concernés**
- `cyber-manager-gardien/LanceurEcran.cs`
- `cyber-manager-gardien/GardienWorker.cs`
- `cyber-manager-gardien/CompteWindows.cs` (ou équivalent)

**Dépendances**
Aucune.

**Tests de validation**
- Connexion en compte standard : l'Écran s'affiche.
- Connexion en compte admin : l'Écran ne s'affiche pas.
- Le gérant peut faire de la maintenance en admin sans être bloqué.

**Pièges à éviter**
- Ne pas se baser sur le nom du compte (règle 1 : pas de nom en dur).
- Vérifier l'appartenance au groupe Administrateurs de Windows.

---

### Élément 2.2 — Nom affiché Windows (FullName)

**Problème**
Windows affiche "Client" sur l'écran de connexion. Le gérant veut voir le nom du poste (ex: "POSTE 5").

**Comportement attendu**
Modifier le FullName du compte (pas le nom SAM, qui casserait le profil). Le Gardien doit pouvoir mettre à jour ce FullName quand le gérant renomme le poste dans l'interface web.

**Fichiers concernés**
- `cyber-manager-gardien/GardienWorker.cs`
- `cyber-manager-gardien/CompteWindows.cs`
- `app/Http/Controllers/Api/AgentController.php` (endpoint pour la commande)
- `app/Http/Controllers/EnregistrementController.php` (déclencher la commande au renommage)
- `cyber-manager-installateur/Program.cs` (définir le FullName à la création)

**Dépendances**
Aucune.

**Tests de validation**
- À la création du compte, le FullName est le nom du poste.
- Quand le gérant renomme le poste dans l'interface, Windows affiche le nouveau nom après quelques secondes.
- Le nom SAM reste inchangé (le profil n'est pas cassé).

**Pièges à éviter**
- Ne pas renommer le compte SAM (casse le profil).
- Ne pas mettre un nom en dur.

---

### Élément 2.3 — Redémarrage après installation

**Problème**
Après installation, il faut redémarrer manuellement pour que l'Écran s'affiche dans la bonne session.

**Comportement attendu**
Si l'installateur tourne dans la session client : pas de redémarrage, l'Écran s'affiche immédiatement. Sinon : redémarrage assisté avec décompte (10-20 secondes) + bouton "Plus tard".

**Fichiers concernés**
- `cyber-manager-installateur/Program.cs`

**Dépendances**
Aucune.

**Tests de validation**
- Installation dans la session client : pas de redémarrage, l'Écran s'affiche.
- Installation dans une autre session : redémarrage proposé avec décompte.
- Le bouton "Plus tard" fonctionne.
- Le redémarrage se fait sans intervention de l'utilisateur.

**Pièges à éviter**
- Ne pas forcer la fermeture des sessions.
- Ne pas redémarrer si ce n'est pas nécessaire.

---

### Élément 2.4 — Faux positifs conflit d'identité

**Problème**
La détection actuelle (2 IP différentes en moins de 12 secondes) déclenche des fausses alertes (DHCP, changement de session).

**Comportement attendu**
Empreinte matérielle (UUID SMBIOS + MAC cartes physiques) qui ne se clone pas.
- Empreinte différente + original présent → clone réel → nouvelle identité automatique.
- Empreinte différente + original absent → remplacement matériel → adoption sans alerte.
- MAC et nom identiques → réinstallation → pas d'alerte.

**Fichiers concernés**
- `cyber-manager-gardien/EmpreinteMaterielle.cs` (ou équivalent)
- `cyber-manager-gardien/Decouverte.cs`
- `app/Http/Controllers/Api/AgentController.php` (comparaison)
- Migration pour ajouter la colonne `empreinte_materielle` à `postes`

**Dépendances**
Aucune.

**Tests de validation**
- Deux PC différents avec la même identité : alerte (vrai clone).
- Réinstallation sur le même PC : pas d'alerte.
- Changement de carte mère : pas d'alerte (adoption).
- Changement d'IP ou de session : pas d'alerte (faux positif éliminé).

**Pièges à éviter**
- Ne pas se baser sur l'IP (règle 1).
- Ne pas se baser sur le nom de machine (règle 1).
- Utiliser une empreinte qui ne se clone pas.

---

## LOT 3 — INTERFACE SERVEUR (AMÉLIORATIONS)

Le LOT 3 est découpé en **12 étapes** (ou BLOCs). Chaque étape est indépendante et testable seule.

### Vue d'ensemble des 12 étapes

| Étape | Contenu | Thème |
|-------|---------|-------|
| Étape 1 | Avatar (composant réutilisable) + avatar dans Personnel | Visuel |
| Étape 2 | Améliorations Personnel (recherche, tri, stats, dates, actif, copier email, réinit mdp, déconnexion partout, modal création, toast) | UX |
| Étape 3 | Carte de profil dans la sidebar + page Mon profil | Visuel |
| Étape 4 | Refonte page de login (logo + couleur + souvenir + erreurs claires + œil) | Visuel |
| Étape 5 | Vérification email à la création + Mot de passe oublié | Sécurité |
| Étape 6 | Sécurité login (throttle, politique mot de passe, expiration session, verrouillage compte) | Sécurité |
| Étape 7 | Historique des connexions (last_login_at, last_login_ip, "Déconnecter partout") | Sécurité |
| Étape 8 | Changer de compte (multi-comptes récents) | UX |
| Étape 9 | Améliorations Installation (détection, feedback copie, compteur postes, liens, mode expert, code visible) | UX |
| Étape 10 | Améliorations Versions (filtre statut, comparaison notes, télécharger archive, compteur, MAJ immédiate, pourcentage à jour) | UX |
| Étape 11 | Améliorations Parc (filtre état, recherche, tri, icônes, modal détail, redémarrer poste, voir logs) | UX |
| Étape 12 | Transversal (mode sombre, raccourcis clavier, toast unifié, barre progression, fil d'Ariane, 404/500 personnalisées, mode maintenance) | UX |

---

### Étape 1 — Avatar (composant réutilisable) + avatar dans Personnel

**Problème**
Pas d'avatar dans la liste du Personnel. Les comptes sont affichés sans photo ni initiales.

**Comportement attendu**
Un composant avatar réutilisable (photo si uploadée, sinon initiales colorées). Affiché dans la liste du Personnel. La couleur est dérivée du nom.

**Fichiers concernés**
- `resources/views/components/avatar.blade.php` (à créer ou modifier)
- `app/Models/User.php` (méthodes `avatarUrl()`, `initiales()`, `couleurAvatar()`)
- `resources/views/parametres/personnel.blade.php`
- Migration pour la colonne `avatar` dans `users`

**Dépendances**
Aucune.

**Tests de validation**
- Un utilisateur sans photo voit ses initiales dans un rond coloré.
- Un utilisateur avec photo voit sa photo.
- La couleur est stable pour un même nom.

**Pièges à éviter**
- Ne pas mettre de couleur en dur (dériver du nom).
- Le composant doit être réutilisable (sidebar, profil, etc.).

---

### Étape 2 — Améliorations Personnel

**Problème**
La page Personnel est fonctionnelle mais austère : pas de recherche, pas de tri, pas de stats, pas de dates, pas d'indicateur "actif", pas de bouton copier email, pas de réinitialisation mot de passe, pas de déconnexion partout, pas de modal de création, pas de toast.

**Comportement attendu**
- Recherche par nom ou email.
- Tri (admin d'abord, nom, dernière connexion).
- Statistiques en haut (X admins, Y personnels, Z en ligne).
- Date de création / dernière connexion sur chaque carte.
- Pastille verte si connecté dans les 5 dernières minutes.
- Bouton "Copier l'email".
- Bouton "Réinitialiser le mot de passe" (génère un mot de passe temporaire).
- Bouton "Déconnecter partout" (révoque tous les tokens).
- Avertissement si on essaie de supprimer le dernier admin.
- Formulaire d'ajout en modal.
- Toast après création.

**Fichiers concernés**
- `app/Http/Controllers/PersonnelController.php`
- `resources/views/parametres/personnel.blade.php`
- `app/Models/User.php` (si nouvelles méthodes nécessaires)
- Migration si nouveaux champs (`last_login_at`, `telephone`, `notes_internes`)

**Dépendances**
Étape 1 (avatar).

**Tests de validation**
- La recherche filtre en temps réel.
- Le tri fonctionne.
- Les stats sont correctes.
- La pastille verte s'affiche pour les comptes en ligne.
- Le bouton copier email copie bien.
- La réinitialisation génère un mot de passe temporaire.
- La déconnexion partout révoque les tokens.
- Le dernier admin ne peut pas être supprimé (bouton grisé + tooltip).
- Le modal s'ouvre et se ferme.
- Le toast s'affiche après création.

**Pièges à éviter**
- Ne pas casser l'édition existante.
- Ne pas mettre de données en dur.

---

### Étape 3 — Carte de profil dans la sidebar + page Mon profil

**Problème**
Pas de carte de profil dans la sidebar. Pas de page "Mon profil" accessible à tous.

**Comportement attendu**
- Carte de profil en bas de la sidebar avec avatar + nom + rôle.
- Menu déroulant au clic : Mon profil / Changer de compte / Se déconnecter.
- Page "Mon profil" autonome (hors interface Paramètres), accessible à tous les utilisateurs (admin et personnel).
- Page permettant de modifier son nom, son email, son avatar, son mot de passe.

**Fichiers concernés**
- `resources/views/layouts/cyber-manager.blade.php`
- `resources/views/components/profil-carte.blade.php` (à créer)
- `app/Http/Controllers/ProfileController.php`
- `resources/views/profile/edit.blade.php`
- `routes/web.php` (route `mon-profil` hors du groupe Paramètres)

**Dépendances**
Étape 1 (avatar).

**Tests de validation**
- La carte de profil s'affiche dans la sidebar.
- Le menu déroulant fonctionne.
- La page Mon profil est accessible à un personnel (pas seulement admin).
- Un personnel ne voit pas les autres onglets Paramètres.
- La modification du profil fonctionne (nom, email, avatar, mot de passe).

**Pièges à éviter**
- Ne pas mettre Mon profil dans l'interface Paramètres (le personnel verrait les autres onglets).
- Le middleware `role:admin` ne doit pas bloquer Mon profil.

---

### Étape 4 — Refonte page de login

**Problème**
La page de login est austère : pas de logo, pas de couleur du cyber, pas de "Se souvenir de moi", pas d'erreurs claires, pas d'icône œil.

**Comportement attendu**
- Logo et nom du cyber (récupérés depuis les paramètres).
- Couleur du cyber appliquée au bouton "Se connecter".
- Fond dégradé ou motif discret (cohérent avec le reste).
- "Se souvenir de moi" (cookie long).
- Lien "Mot de passe oublié ?".
- Message d'erreur clair : "Email ou mot de passe incorrect" (pas de détail).
- Icône œil pour afficher/masquer le mot de passe.
- Indicateur de force du mot de passe (à la création).

**Fichiers concernés**
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/guest.blade.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`

**Dépendances**
Étape 3 (si le logo/couleur viennent des paramètres).

**Tests de validation**
- Le logo et le nom du cyber s'affichent.
- La couleur du cyber est appliquée au bouton.
- "Se souvenir de moi" fonctionne (session longue).
- Le lien "Mot de passe oublié ?" fonctionne.
- Le message d'erreur est clair et neutre.
- L'icône œil fonctionne.

**Pièges à éviter**
- Ne pas révéler si l'email existe ou non.
- Ne pas mettre de couleur en dur (récupérer depuis les paramètres).

---

### Étape 5 — Vérification email à la création + Mot de passe oublié

**Problème**
À la création d'un compte, il n'y a pas de vérification email. Pas de "Mot de passe oublié ?" sur la page de login.

**Comportement attendu**
- À la création d'un compte, un code à 6 chiffres est généré.
- Si le SMTP est configuré : le code est envoyé par email.
- Si le SMTP n'est pas configuré : le code est affiché à l'admin dans la liste du Personnel (pour qu'il le transmette).
- Le compte est inactif jusqu'à ce que l'email soit vérifié.
- Le personnel se connecte : il est bloqué sur une page "Vérifiez votre email" (avec le code à saisir).
- Il saisit le code : il est redirigé vers le dashboard.
- Page de login : lien "Mot de passe oublié ?" → saisie email → code envoyé → saisie code + nouveau mot de passe → retour au login.

**Sous-parties (déjà spécifiées par DeepSeek)**
- 5.1 — Migration `add_verification_codes_to_users_table`
- 5.2 — `User.php` (méthodes de vérification)
- 5.3 — `PersonnelController.php` (envoi du code)
- 5.4 — `VerificationCodeController.php`
- 5.5 — `PasswordResetCodeController.php`
- 5.6 — `routes/auth.php` (fichier complet, sans "remplace si existe")
- 5.7 — `routes/web.php` (middleware email vérifié)
- 5.8 — `resources/views/auth/verify-code.blade.php`
- 5.9 — `resources/views/auth/forgot-password.blade.php`
- 5.10 — `resources/views/auth/reset-password-code.blade.php`
- 5.11 — `resources/views/auth/login.blade.php` (lien Mot de passe oublié)
- 5.12 — `resources/views/parametres/personnel.blade.php` (badge Email non vérifié + code)

**Fichiers concernés**
Voir sous-parties ci-dessus.

**Dépendances**
Étape 4 (page de login).

**Tests de validation**
- Créer un personnel : un code s'affiche (ou un email est envoyé).
- Le badge "Email non vérifié" s'affiche dans la liste.
- Le personnel se connecte : il est bloqué sur "Vérifiez votre email".
- Il saisit le code : il est redirigé vers le dashboard.
- Le badge "Email non vérifié" disparaît.
- Mot de passe oublié : le code est envoyé, la réinitialisation fonctionne.

**Pièges à éviter**
- Ne pas dire "Remplace les anciennes routes si elles existent" (travail à l'aveugle).
- Donner le fichier `routes/auth.php` complet.
- Ne pas casser les routes existantes.
- Si le SMTP n'est pas configuré, afficher le code à l'admin (sinon le personnel ne peut pas vérifier).

---

### Étape 6 — Sécurité login

**Problème**
Pas de limite de tentatives de connexion, pas de politique de mot de passe, pas d'expiration de session, pas de verrouillage de compte.

**Comportement attendu**
- Limite de tentatives : 5 essais par minute, puis blocage 15 minutes.
- Politique de mot de passe : 8 caractères min, 1 majuscule, 1 chiffre, pas dans la liste des mots de passe courants.
- Expiration de session : déconnexion auto après X minutes d'inactivité (configurable).
- Verrouillage de compte après X tentatives échouées (déblocage par admin).

**Fichiers concernés**
- `app/Http/Requests/Auth/LoginRequest.php`
- `config/session.php`
- Migration pour les champs de verrouillage dans `users`
- `app/Http/Controllers/PersonnelController.php` (déblocage)

**Dépendances**
Étape 5 (système d'authentification).

**Tests de validation**
- 6 tentatives échouées : blocage 15 minutes.
- Mot de passe trop faible : refusé à la création.
- Inactivité prolongée : déconnexion automatique.
- Compte verrouillé : l'admin peut le débloquer.

**Pièges à éviter**
- Ne pas bloquer définitivement (toujours permettre le déblocage).
- Ne pas mettre les valeurs en dur (configurables).

---

### Étape 7 — Historique des connexions

**Problème**
Pas d'historique des connexions. Pas de bouton "Déconnecter partout".

**Comportement attendu**
- `last_login_at`, `last_login_ip` affichés sur chaque carte.
- Bouton "Déconnecter partout" (révoque tous les tokens d'un compte).
- Historique des connexions (date, heure, IP).

**Fichiers concernés**
- Migration pour `last_login_at`, `last_login_ip` dans `users`
- `app/Models/User.php`
- `resources/views/parametres/personnel.blade.php`
- `app/Http/Controllers/PersonnelController.php`

**Dépendances**
Étape 2 (carte Personnel).

**Tests de validation**
- La date et l'IP de dernière connexion s'affichent.
- Le bouton "Déconnecter partout" révoque les tokens.
- L'utilisateur déconnecté doit se reconnecter.

**Pièges à éviter**
- Ne pas stocker l'IP en dur.
- Ne pas exposer l'IP complète à tous (seulement à l'admin).

---

### Étape 8 — Changer de compte

**Problème**
Pas de moyen rapide de changer de compte.

**Comportement attendu**
- Liste des derniers comptes utilisés sur ce PC (comme Windows).
- Bouton "Changer de compte" qui déconnecte et propose la liste.

**Fichiers concernés**
- `resources/views/auth/login.blade.php`
- Un nouveau contrôleur `ChangerCompteController.php` (à créer)

**Dépendances**
Étape 4 (page de login).

**Tests de validation**
- Les derniers comptes utilisés s'affichent.
- Le clic sur un compte pré-remplit l'email.
- "Changer de compte" déconnecte et propose la liste.

**Pièges à éviter**
- Ne pas stocker le mot de passe.
- Ne pas stocker plus que nécessaire (RGPD).

---

### Étape 9 — Améliorations Installation

**Problème**
La page `/parametres/installation` est fonctionnelle mais austère : pas de détection automatique, pas de feedback copie, pas de compteur de postes, pas de liens, pas de mode expert, pas de code visible.

**Comportement attendu**
- Détection automatique : vérifier si les fichiers sont là et alerter si manquants.
- Feedback visuel sur le bouton "Copier le chemin".
- Compteur de postes installés : "X postes déjà installés, Y cette semaine".
- Lien "Voir les postes" à la fin de chaque étape.
- Mode expert : afficher les commandes en ligne (pour technicien).
- Rappel du code d'installation dans un bloc visible (pas seulement dans l'assistant).

**Fichiers concernés**
- `resources/views/parametres/installation.blade.php`
- `resources/views/parametres/installation/_etapes.blade.php`
- `resources/views/parametres/installation/_fichiers.blade.php`
- `app/Http/Controllers/InstallationController.php`

**Dépendances**
Aucune.

**Tests de validation**
- Les fichiers manquants sont signalés.
- Le feedback copie fonctionne.
- Le compteur est correct.
- Les liens fonctionnent.
- Le mode expert affiche les commandes.
- Le code d'installation est visible.

**Pièges à éviter**
- Ne pas mettre de chemin en dur.
- Ne pas casser l'assistant existant.

---

### Étape 10 — Améliorations Versions

**Problème**
La page `/parametres/versions` est fonctionnelle mais austère : pas de filtre, pas de comparaison, pas de téléchargement d'archive, pas de compteur, pas de MAJ immédiate, pas de pourcentage à jour.

**Comportement attendu**
- Filtre par statut (toutes / brouillon / pilote / publiée / archivée).
- Comparaison de versions : voir ce qui a changé entre 2 versions.
- Télécharger une version archivée.
- Compteur par statut en haut.
- Bouton "Mettre à jour maintenant" (forcer les postes à se mettre à jour immédiatement).
- Pourcentage de postes à jour (X postes sur Y).

**Fichiers concernés**
- `resources/views/parametres/versions.blade.php`
- `app/Http/Controllers/VersionsController.php`

**Dépendances**
Aucune.

**Tests de validation**
- Le filtre fonctionne.
- La comparaison affiche les notes.
- Le téléchargement d'archive fonctionne.
- Le compteur est correct.
- La MAJ immédiate fonctionne.
- Le pourcentage est correct.

**Pièges à éviter**
- Ne pas casser l'existant.
- Ne pas mettre de données en dur.

---

### Étape 11 — Améliorations Parc

**Problème**
La page `/parametres/parc` est fonctionnelle mais austère : pas de filtre, pas de recherche, pas de tri, pas d'icônes, pas de modal détail, pas de redémarrage, pas de logs.

**Comportement attendu**
- Filtre par état (en ligne / hors ligne / occupé / verrouillé).
- Recherche par nom de poste ou IP.
- Tri par nom, état, version, dernière mise à jour.
- Indicateur visuel (icône) pour chaque état.
- Bouton "Voir le détail" : modal avec toutes les infos d'un poste (MAC, IP, empreinte, dernière session, etc.).
- Bouton "Redémarrer le poste" (via une commande agent).
- Bouton "Voir les logs" du poste (dernières commandes, dernières erreurs).

**Fichiers concernés**
- `resources/views/parametres/parc.blade.php`
- `app/Http/Controllers/ParcController.php`

**Dépendances**
Aucune.

**Tests de validation**
- Le filtre fonctionne.
- La recherche fonctionne.
- Le tri fonctionne.
- Les icônes s'affichent.
- La modal détail s'ouvre et affiche les bonnes infos.
- Le redémarrage fonctionne.
- Les logs s'affichent.

**Pièges à éviter**
- Ne pas mettre d'IP en dur.
- Ne pas casser l'existant.

---

### Étape 12 — Transversal

**Problème**
Pas de mode sombre, pas de raccourcis clavier, pas de toast unifié, pas de barre de progression, pas de fil d'Ariane, pas de 404/500 personnalisées, pas de mode maintenance.

**Comportement attendu**
- Mode sombre (toggle dans la sidebar).
- Raccourcis clavier (Ctrl+K pour la recherche globale, Échap pour fermer).
- Notifications toast unifiées (un seul système).
- Barre de progression en haut lors des chargements longs.
- Fil d'Ariane (breadcrumb) dans Paramètres.
- Page 404 personnalisée avec le logo du cyber.
- Page 500 personnalisée (erreur serveur, avec lien vers le dashboard).
- Mode maintenance avec page dédiée (quand on met à jour le serveur).

**Fichiers concernés**
- `resources/views/layouts/cyber-manager.blade.php`
- `resources/views/errors/404.blade.php` (à créer)
- `resources/views/errors/500.blade.php` (à créer)
- `resources/views/errors/503.blade.php` (à créer)
- `app/Http/Middleware/MaintenanceMiddleware.php` (à créer)
- `resources/js/app.js`

**Dépendances**
Aucune.

**Tests de validation**
- Le mode sombre fonctionne et est mémorisé.
- Les raccourcis clavier fonctionnent.
- Les toasts sont unifiés.
- La barre de progression s'affiche.
- Le fil d'Ariane s'affiche.
- La 404 est personnalisée.
- La 500 est personnalisée.
- Le mode maintenance fonctionne.

**Pièges à éviter**
- Ne pas casser l'existant.
- Ne pas mettre de logo en dur (récupérer depuis les paramètres).

---

## LOT 4 — INSTALLATEUR + DÉSINSTALLEUR

### Élément 4.1 — Installateur moderne

**Problème**
L'installateur actuel est fonctionnel mais austère : pas d'étapes, pas de progression, pas de design, pas de choix de scénario de compte.

**Comportement attendu**
- Assistant en 4 étapes : Bienvenue → Configuration → Progression → Terminé.
- Design cohérent avec l'interface web (violet, icônes, ombres).
- Barre de progression détaillée avec journal en temps réel.
- 4 scénarios de compte Windows :
  1. Créer un nouveau compte (nommé d'après le poste, ex: "POSTE 5").
  2. Utiliser un compte existant (liste + vérification admin/standard).
  3. Configurer le compte actuel (avertissement si admin).
  4. Ne pas configurer de compte (manuel).
- Mode silencieux `--reboot` pour déploiement en série.
- Détection de la session (client ou admin) pour décider du redémarrage.
- Bouton "Fermer" à la fin.

**Fichiers concernés**
- `cyber-manager-installateur/Program.cs` (refonte complète)
- `cyber-manager-installateur/Installateur.csproj` (ressources)

**Dépendances**
LOT 2 (compte Windows, FullName, redémarrage).

**Tests de validation**
- Les 4 étapes s'enchaînent.
- Le design est cohérent.
- La progression s'affiche.
- Les 4 scénarios fonctionnent.
- Le mode silencieux fonctionne.
- Le redémarrage est assisté.
- Le bouton "Fermer" fonctionne.

**Pièges à éviter**
- Ne pas imposer un scénario.
- Ne pas forcer la fermeture des sessions.
- Ne pas mettre de nom en dur.

---

### Élément 4.2 — Désinstalleur

**Problème**
Aucun désinstalleur. Le gérant ne peut pas retirer proprement l'agent.

**Comportement attendu**
Un programme qui :
- Arrête le service Gardien.
- Supprime les fichiers (Program Files, %LocalAppData%).
- Restaure l'état d'origine (comptes Windows, pare-feu).
- Supprime le poste côté serveur.
- Affiche une confirmation avant de tout supprimer.

**Fichiers concernés**
- Nouveau projet `cyber-manager-desinstalleur/` (C#)
- Endpoint API `DELETE /api/agent/postes/{id}` (supprimer le poste côté serveur)

**Dépendances**
LOT 4.1 (installateur).

**Tests de validation**
- Le service est arrêté.
- Les fichiers sont supprimés.
- Les comptes Windows sont restaurés (ou laissés propres).
- Le pare-feu est restauré.
- Le poste est supprimé côté serveur.
- Une confirmation est demandée avant.

**Pièges à éviter**
- Ne pas supprimer les données utilisateur sans confirmation.
- Ne pas laisser de traces.

---

## LOT 5 — VOYANT RÉSEAU + FINITIONS

### Élément 5.1 — Icône réseau Windows (NCSI)

**Problème**
Windows affiche "Pas de connexion Internet" alors que le serveur local est joignable. C'est normal (portail captif), mais anxiogène pour le gérant.

**Comportement attendu**
- Toujours : afficher dans le widget et l'Écran un voyant explicite ("Serveur : connecté ✓" / "Serveur : injoignable").
- Optionnel : rediriger la sonde NCSI vers le serveur (à tester, non bloquant).

**Fichiers concernés**
- `cyber-manager-agent/SessionWidget.cs`
- `cyber-manager-agent/VoyantServeur.cs`
- `cyber-manager-agent/ClientWindow.cs`
- Optionnel : `cyber-manager-gardien/RestrictionsWindows.cs` (NCSI)

**Dépendances**
LOT 1.2 (perte de communication).

**Tests de validation**
- Le voyant s'affiche dans le widget.
- Le voyant s'affiche dans l'Écran.
- Le voyant passe au rouge quand le serveur est injoignable.
- Le voyant repasse au vert quand le serveur revient.
- (Optionnel) Windows affiche "connecté" même sans Internet.

**Pièges à éviter**
- Ne pas cacher le problème (expliquer).
- Ne pas dépendre du NCSI (optionnel).

---

### Élément 5.2 — Vérifications finales

**Problème**
Il faut vérifier que tout fonctionne ensemble, dans tous les cas.

**Comportement attendu**
- Test sur PC neuf (aucun compte configuré).
- Test sur PC déjà configuré (compte admin + compte client existants).
- Test sur PC cloné.
- Test avec plusieurs cartes réseau.
- Test avec plusieurs sessions Windows.
- Test de perte de communication (câble débranché, Wi-Fi coupé, serveur arrêté).
- Test de changement d'adresse IP du serveur.
- Test de changement de routeur.

**Fichiers concernés**
Aucun (c'est une phase de test).

**Dépendances**
Tous les lots.

**Tests de validation**
Voir ci-dessus.

**Pièges à éviter**
- Ne pas se contenter d'un seul cas.
- Ne pas supposer que ça marche.

---

## PARTIE 3 — MÉTHODE DE TRAVAIL POUR L'IA QUI REPREND

### 3.1 Principe général

Le travail se fait **lot par lot**, puis **étape par étape** dans chaque lot.

Pour chaque étape :
1. **Sauvegarde Git** (commit + push dans les 5 dossiers).
2. **Génération du fichier de contexte** avec `projet_generator.py`.
3. **Envoi du prompt à l'IA** (Claude ou autre) avec le fichier de contexte.
4. **Réception du code complet** (fichiers entiers, pas de "remplace si existe").
5. **Application du code** (copier/coller).
6. **Test** de l'étape.
7. **Si OK** : commit + push.
8. **Si KO** : `git revert` ou `git checkout -- .` pour revenir en arrière.

### 3.2 Sauvegarde Git

Avant chaque étape, exécuter dans les 5 dossiers :

```powershell
cd E:\xampp\htdocs\cyber-manager
git add .
git commit -m "Sauvegarde avant [nom de l'étape]"
git push