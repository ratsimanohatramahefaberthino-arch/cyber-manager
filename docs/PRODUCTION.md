# Passer Cyber Manager en production

## Méthode simple (recommandée) — sans terminal
Interface web > bannière « Mode test actif » (ou /parametres/production) > « Passer en mode production ».
Le logiciel sauvegarde `.env` dans `storage/app/sauvegardes-env/` (5 dernières copies) puis règle :
APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning, et vide le cache de configuration.

## Méthode manuelle (technicien)
Dans `.env` : APP_ENV=production · APP_DEBUG=false · LOG_LEVEL=warning, puis `php artisan config:clear`.
Un modèle est fourni : `.env.production.example` (ne contient aucun secret).

## MySQL (XAMPP) — optionnel, technicien
Par défaut XAMPP utilise « root » sans mot de passe. Pour le renforcer :
1. phpMyAdmin > Comptes utilisateurs > root > Changer le mot de passe.
2. Reporter le mot de passe dans `.env` (DB_PASSWORD) ET dans `C:\xampp\phpMyAdmin\config.inc.php`
   (sinon phpMyAdmin ne se connecte plus).
3. `php artisan config:clear`.
Alternative : créer un utilisateur dédié limité à la base `cyber_manager` et l'utiliser dans `.env`.

## Routeur
Les accès au routeur se saisissent dans l'interface (Paramètres > Portail Captif > Réseau). Rien n'est stocké dans le code.
Si ces accès ont déjà figuré dans le code ou dans un dépôt, changez le mot de passe du routeur.