# PROJET : CYBER MANAGER

## 1. CONTEXTE

Je suis étudiant en L3 Informatique Générale à l'ENI de Toliara, Madagascar.

Je développe dans le cadre de mon stage un logiciel appelé **Cyber Manager** destiné à la gestion d'un cybercafé.

Le projet doit d'abord résoudre les besoins du cybercafé où je réalise mon stage, mais l'architecture doit être suffisamment générique pour pouvoir être utilisée plus tard dans d'autres cybercafés.

Le système actuel utilise notamment :

* MikroTik ;
* Mikhmon pour le HotSpot/Wi-Fi ;
* Cybera Client pour les postes Ethernet ;
* des feuilles/papiers et Excel pour certaines opérations.

L'objectif de Cyber Manager est progressivement de réunir ces fonctionnalités dans un système centralisé.

---

## 2. OBJECTIF FINAL

Cyber Manager doit permettre depuis une interface centrale :

* gérer les postes Ethernet ;
* gérer les clients Wi-Fi ;
* gérer le HotSpot MikroTik ;
* créer et gérer des vouchers ;
* gérer les sessions ;
* gérer les tarifs ;
* démarrer/arrêter/étendre des sessions ;
* gérer les expirations ;
* gérer les restitutions ;
* consulter l'historique ;
* consulter les statistiques ;
* gérer les utilisateurs administrateurs ;
* communiquer avec des agents Windows ;
* communiquer avec MikroTik via son API.

Architecture cible :

CYBER MANAGER
│
├── Interface centrale d'administration
│
├── MikroTik / HotSpot
│     └── Clients Wi-Fi
│
└── Agents Windows
└── Postes Ethernet

---

## 3. RÈGLE ARCHITECTURALE ABSOLUE

NE JAMAIS construire le logiciel en dépendant de valeurs matérielles spécifiques.

Le projet doit être configurable.

Ne jamais considérer comme définitifs :

* l'adresse IP du MikroTik de test ;
* l'adresse IP du MikroTik de production ;
* les noms des postes ;
* les adresses MAC ;
* le nom du cyber ;
* les SSID ;
* les interfaces MikroTik ;
* les identifiants API ;
* les profils HotSpot ;
* les tarifs ;
* les noms des ordinateurs ;
* les ports réseau.

Ces valeurs doivent être placées dans :

* configuration ;
* base de données ;
* variables d'environnement ;
* paramètres administrables.

Exemple :

Le MikroTik de TEST utilise actuellement 128.0.1.1.

Cela ne signifie PAS que l'application doit coder en dur 128.0.1.1.

En production, l'adresse peut être différente.

---

## 4. ENVIRONNEMENT ACTUEL

Projet Laravel :

E:\xampp\htdocs\cyber-manager

Environnement :

* Windows ;
* XAMPP ;
* PHP 8.2.12 ;
* MariaDB 10.4.32 ;
* Composer 2.10.3 ;
* Laravel 12.69.2 ;
* VS Code.

URL locale actuelle :

http://127.0.0.1:8000

Agent Windows :

E:\xampp\htdocs\cyber-manager-agent

API locale actuelle :

http://127.0.0.1:5005

Endpoints existants :

GET /api/status
POST /api/commande

Commandes actuellement prévues :

* PING
* STATUS
* LOCK_TEST
* UNLOCK_TEST

L'agent possède déjà une authentification par token.

---

## 5. BASE DE DONNÉES / BACKEND EXISTANT

Le projet Laravel possède déjà des modèles/services/migrations liés notamment à :

* postes ;
* sessions ;
* tarifs ;
* recharges ;
* restitutions ;
* expirations ;
* historiques ;
* vouchers.

Il existe déjà une table/model de postes avec notamment des informations telles que :

* nom ;
* nom Windows ;
* MAC ;
* IP ;
* type de connexion ;
* état ;
* actif ;
* dernière communication.

Il existe également déjà une logique de vouchers, mais elle est encore principalement locale à l'application et doit progressivement être reliée au vrai MikroTik.

IMPORTANT :

Avant de créer ou modifier une fonctionnalité, analyser les fichiers existants et réutiliser ce qui est valable.

NE PAS réécrire inutilement tout le projet.

---

## 6. POSTES ACTUELS

Des postes de test/exemple existent actuellement dans la base :

POSTE1
POSTE2
POSTE3
POSTE7

POSTE7 possède actuellement une configuration d'agent local de test :

http://127.0.0.1:5005

Ces valeurs sont des données de test.

Elles ne doivent jamais être transformées en constantes globales du logiciel.

---

## 7. AGENT WINDOWS

L'agent est un projet séparé.

Il doit éventuellement permettre :

* vérifier l'état du poste ;
* communiquer avec le serveur ;
* recevoir des commandes autorisées ;
* verrouiller un poste ;
* déverrouiller un poste ;
* signaler sa présence ;
* gérer les erreurs ;
* fonctionner de manière fiable.

Le serveur central doit pouvoir détecter lorsqu'un agent est :

* connecté ;
* déconnecté ;
* non joignable.

L'agent ne doit pas devenir une dépendance pour le Wi-Fi.

---

## 8. RÉSEAU DE PRODUCTION

Le cybercafé possède actuellement :

Starlink
↓
MikroTik PRODUCTION
↓
Switch
├── serveur/admin
├── postes Ethernet
└── Wi-Fi

MikroTik PRODUCTION :

* modèle : hAP ac² ;
* RouterOS : 6.49.17 ;
* IP LAN actuelle : 128.1.1.1 ;
* réseau : 128.1.0.0/22 ;
* HotSpot : hotspot1 ;
* bridge HotSpot : bridge_ap ;
* SSID actuels : IZARA2G et IZARA5G ;
* Mikhmon : 128.1.0.7.

ATTENTION :

Ce MikroTik est utilisé dans un environnement réel avec des clients.

NE PAS modifier sa configuration pendant le développement sauf instruction explicite et procédure de test contrôlée.

---

## 9. MIKROTIK DE TEST

Un deuxième MikroTik est utilisé comme environnement de laboratoire.

Identity :
INSIDE

IP LAN :
128.0.1.1

RouterOS :
7.21.3

Réseau :
128.0.1.0/24

HotSpot :
hotspot1

Bridge :
bridge1

SSID prévus :
INSIDE2G
INSIDE5G

Les radios Wi-Fi du MikroTik TEST ont actuellement été désactivées temporairement pendant la phase de sécurisation réseau.

---

## 10. CONNEXION DU MIKROTIK DE TEST

Le montage de laboratoire actuel est :

PC
├── Wi-Fi → MikroTik PRODUCTION → Internet
│
└── Ethernet → MikroTik TEST → MikroTik PRODUCTION → Internet

Le MikroTik TEST reçoit actuellement une adresse WAN depuis le MikroTik PRODUCTION.

Le réseau de test a déjà été validé.

Tests réalisés avec succès :

* TEST → passerelle production ;
* TEST → 8.8.8.8 ;
* TEST → google.com ;
* PC → MikroTik TEST ;
* PC → port API 8728.

Test actuel :

Test-NetConnection 128.0.1.1 -Port 8728

Résultat :

TcpTestSucceeded : True

Donc le PC peut actuellement atteindre l'API RouterOS du MikroTik TEST sur le port 8728.

---

## 11. API MIKROTIK

L'API RouterOS utilisée pour le développement est actuellement :

TCP 8728.

L'accès API du MikroTik TEST a été restreint au réseau/adresse du PC de développement.

L'intégration doit être faite proprement dans une couche de service dédiée.

NE PAS mettre les commandes RouterOS directement dans les contrôleurs ou les vues.

Créer une abstraction claire du type :

MikrotikService
MikrotikConnection
ou architecture équivalente.

Les identifiants doivent être stockés dans .env ou une configuration sécurisée.

NE JAMAIS écrire un mot de passe réel dans le code source.

---

## 12. WI-FI / HOTSPOT

Le MikroTik doit rester responsable des fonctions réseau :

* DHCP ;
* HotSpot ;
* authentification ;
* routage ;
* accès Internet.

Cyber Manager doit gérer principalement la logique métier :

* vouchers ;
* sessions ;
* durée ;
* paiement ;
* expiration ;
* historique ;
* administration.

Ne pas essayer de remplacer inutilement le rôle du MikroTik.

---

## 13. VOUCHERS

Le système actuel doit progressivement passer de vouchers uniquement locaux à de vrais utilisateurs HotSpot MikroTik.

Architecture cible :

Cyber Manager
↓
MikrotikService
↓
RouterOS API
↓
HotSpot User

Le voucher doit être cohérent entre :

* base de données Cyber Manager ;
* MikroTik.

---

## 14. POSTES ETHERNET

Les postes Ethernet doivent progressivement être gérés par l'agent Windows.

Architecture :

Cyber Manager
↓
Agent Windows
↓
PC client

L'application centrale doit pouvoir connaître :

* état ;
* disponibilité ;
* session ;
* temps restant ;
* montant ;
* dernière communication.

---

## 15. TARIFS

Les tarifs doivent être configurables.

Des valeurs de test actuellement envisagées sont par exemple :

300 Ar → 15 min
500 Ar → 25 min
600 Ar → 30 min
1000 Ar → 50 min

Ces valeurs ne doivent PAS être codées en dur.

Elles doivent être stockées en base/configuration.

---

## 16. SESSIONS

Wi-Fi et Ethernet doivent utiliser autant que possible un même moteur métier de session.

Concept :

Session
├── Wi-Fi
└── Ethernet

Une session peut avoir :

* client ;
* type ;
* équipement ;
* début ;
* durée ;
* fin prévue ;
* temps restant ;
* montant ;
* statut ;
* historique.

---

## 17. EXTENSIONS

Une session doit pouvoir être prolongée.

Exemple :

30 minutes achetées
↓
8 minutes restantes
↓
+25 minutes
↓
33 minutes restantes.

L'opération doit être enregistrée dans l'historique.

---

## 18. RESTITUTIONS

Une restitution doit être traçable.

Elle doit conserver notamment :

* montant initial ;
* durée initiale ;
* durée consommée ;
* durée restante ;
* montant restitué ;
* motif ;
* opérateur ;
* date.

---

## 19. HISTORIQUE

Les opérations importantes doivent être enregistrées.

Exemples :

* création de session ;
* connexion ;
* déconnexion ;
* recharge ;
* expiration ;
* restitution ;
* création de voucher ;
* modification de tarif ;
* action administrateur.

---

## 20. INTERFACE CENTRALE

L'interface doit être professionnelle, claire, moderne et ergonomique.

Elle doit progressivement contenir :

* Dashboard ;
* Postes ;
* Wi-Fi ;
* Sessions ;
* Vouchers ;
* Tarifs ;
* Clients ;
* Paiements ;
* Restitutions ;
* Historique ;
* Statistiques ;
* Configuration ;
* Utilisateurs.

L'interface doit être responsive lorsque cela est pertinent.

---

## 21. OBJECTIF D'ARCHITECTURE

Séparer :

1. présentation ;
2. logique métier ;
3. accès aux données ;
4. intégrations externes.

Exemple :

Interface
↓
Controller
↓
Service métier
↓
Repository / Model / Integration
↓
MikroTik ou Agent

Éviter de mettre toute la logique dans les Controllers.

---

## 22. RÈGLE DE TRAVAIL AVEC L'EXISTANT

Avant toute modification importante :

1. analyser les fichiers existants ;
2. identifier les fonctionnalités déjà présentes ;
3. identifier les dépendances ;
4. conserver ce qui fonctionne ;
5. modifier seulement ce qui est nécessaire ;
6. éviter les duplications ;
7. ne pas supprimer une fonctionnalité existante sans raison ;
8. tester après chaque modification.

Ne pas supposer qu'un fichier n'existe pas avant de l'avoir recherché.

---

## 23. RÈGLE DE COMPATIBILITÉ

Le développement doit être testé au minimum avec :

* MikroTik RouterOS 7.x sur le routeur TEST ;
* MikroTik RouterOS 6.x sur le routeur de production.

Ne pas supposer qu'une fonctionnalité disponible dans RouterOS 7 fonctionne exactement de la même façon dans RouterOS 6.

Lorsqu'une différence existe, isoler cette différence dans la couche MikroTik au lieu de contaminer toute l'application.

---

## 24. RÈGLE DE SÉCURITÉ

Ne jamais :

* exposer les mots de passe ;
* coder les secrets en dur ;
* modifier le MikroTik production sans autorisation ;
* supprimer les données existantes sans sauvegarde ;
* effectuer une opération destructive sans avertissement ;
* utiliser le réseau de production comme laboratoire.

Le MikroTik TEST est le laboratoire principal.

---

## 25. MÉTHODE DE DÉVELOPPEMENT

Le projet sera développé par étapes indépendantes.

Chaque étape possède :

* un objectif ;
* des entrées ;
* des fonctionnalités ;
* des fichiers concernés ;
* des tests ;
* des critères de validation.

À la fin de chaque étape, produire un court rapport :

IMPLEMENTED.md

contenant :

* ce qui a été fait ;
* fichiers modifiés ;
* migrations ajoutées ;
* commandes à exécuter ;
* tests effectués ;
* résultats ;
* problèmes restants ;
* prochaine étape recommandée.

Ne jamais considérer une étape terminée sans tests.

---

## 26. PRIORITÉ GÉNÉRALE

Ordre recommandé :

0. Audit de l'existant
1. Socle + architecture MikroTik
2. Interface centrale de supervision
3. Intégration HotSpot Wi-Fi
4. Vouchers Wi-Fi
5. Sessions Wi-Fi
6. Agent Windows
7. Sessions Ethernet
8. Tarifs / paiements / extensions / restitutions
9. Administration complète
10. Historique / statistiques
11. Sécurité / robustesse
12. Déploiement / documentation

---

## 27. IMPORTANT POUR CHAQUE NOUVELLE SESSION CLAUDE

Si tu reçois ce contexte dans une nouvelle conversation, ne recommence pas automatiquement le projet depuis zéro.

Commence par :

1. lire ce contexte ;
2. examiner la structure actuelle du projet ;
3. examiner les fichiers réellement présents ;
4. déterminer l'étape demandée ;
5. vérifier les dépendances avec les fonctionnalités existantes ;
6. implémenter uniquement cette étape ;
7. tester ;
8. documenter les modifications.

Si une information manque dans le code fourni, indique précisément ce qui manque au lieu d'inventer.

FIN DU CONTEXTE MAÎTRE.