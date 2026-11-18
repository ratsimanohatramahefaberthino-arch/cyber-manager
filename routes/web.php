<?php

use App\Http\Controllers\HotspotController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PosteController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\WifiController;
use App\Http\Controllers\MikroTikStatusController;
use App\Http\Controllers\WifiAlarmController;
use App\Http\Controllers\PortailController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\HistoriqueController;
use App\Http\Controllers\StatistiqueController;
use App\Http\Controllers\EnregistrementController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\VersionsController;
use App\Http\Controllers\ParcController;

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Routes protégées (auth) — admin ET personnel
| Les blocs « $admin » sont réservés à l'administrateur.
|--------------------------------------------------------------------------
*/

$admin = RoleMiddleware::class . ':admin';

Route::middleware(['auth', 'verified.email'])->group(function () use ($admin) {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Tarifs : consultation pour tous, modification réservée à l'admin
    Route::get('/tarifs', [TarifController::class, 'index'])->name('tarifs.index');
    Route::post('/tarifs/simuler', [TarifController::class, 'simuler'])->name('tarifs.simuler');

    Route::middleware($admin)->group(function () {
        Route::post('/tarifs/passer-separe', [TarifController::class, 'passerSepare'])->name('tarifs.passer-separe');
        Route::post('/tarifs/passer-unifie', [TarifController::class, 'passerUnifie'])->name('tarifs.passer-unifie');
        Route::post('/tarifs/reinitialiser', [TarifController::class, 'reinitialiser'])->name('tarifs.reinitialiser');
        Route::patch('/tarifs/{tarif}', [TarifController::class, 'update'])->name('tarifs.update');
        Route::post('/tarifs/{tarif}/raccourcis', [TarifController::class, 'storeRaccourci'])->name('tarifs.raccourcis.store');
        Route::delete('/tarifs/{tarif}/raccourcis/{raccourci}', [TarifController::class, 'destroyRaccourci'])->name('tarifs.raccourcis.destroy');
    });

    // Sessions
    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');

    // Postes
    Route::get('/postes',       [PosteController::class, 'index'])->name('postes.index');
    Route::get('/postes/etat',  [PosteController::class, 'etat'])->name('postes.etat');

    Route::post('/postes/raccourcis',                [PosteController::class, 'ajouterRaccourci'])->name('postes.raccourcis.store');
    Route::post('/postes/raccourcis/{id}/supprimer', [PosteController::class, 'supprimerRaccourci'])->name('postes.raccourcis.destroy');

    Route::get('/postes/{poste}/chat',         [PosteController::class, 'chat'])->name('postes.chat');
    Route::post('/postes/{poste}/message',     [PosteController::class, 'envoyerMessage'])->name('postes.message');

    Route::post('/postes/{poste}/demarrer',    [PosteController::class, 'demarrer'])->name('postes.demarrer');
    Route::post('/postes/{poste}/fermer',      [PosteController::class, 'fermer'])->name('postes.fermer');
    Route::post('/postes/{poste}/suspendre',   [PosteController::class, 'suspendre'])->name('postes.suspendre');
    Route::post('/postes/{poste}/reprendre',   [PosteController::class, 'reprendre'])->name('postes.reprendre');
    Route::post('/postes/{poste}/verrouiller', [PosteController::class, 'verrouiller'])->name('postes.verrouiller');
    Route::post('/postes/{poste}/debloquer',   [PosteController::class, 'debloquer'])->name('postes.debloquer');
    Route::post('/postes/{poste}/desactiver',  [PosteController::class, 'desactiver'])->name('postes.desactiver');
    Route::post('/postes/{poste}/activer',     [PosteController::class, 'activer'])->name('postes.activer');
    Route::post('/postes/{poste}/arreter',     [PosteController::class, 'arreter'])->name('postes.arreter');
    Route::post('/postes/{poste}/recharger',   [PosteController::class, 'recharger'])->name('postes.recharger');

    // ADMIN : enregistrement, paramètres, personnel, versions, installation
    Route::middleware($admin)->group(function () {
        // Enregistrement
        Route::get('/enregistrement',                          [EnregistrementController::class, 'index'])->name('enregistrement.index');
        Route::post('/enregistrement/code',                    [EnregistrementController::class, 'regenererCode'])->name('enregistrement.code');
        Route::post('/enregistrement/postes/{poste}/renommer', [EnregistrementController::class, 'renommer'])->name('enregistrement.renommer');
        Route::post('/enregistrement/postes/{poste}/separer',  [EnregistrementController::class, 'separer'])->name('enregistrement.separer');
        Route::post('/enregistrement/postes/{poste}/ignorer',  [EnregistrementController::class, 'ignorerConflit'])->name('enregistrement.ignorer');

        // Paramètres généraux
        Route::get('/parametres',         [ParametresController::class, 'index'])->name('parametres.index');
        Route::post('/parametres',        [ParametresController::class, 'update'])->name('parametres.update');
        Route::delete('/parametres/logo', [ParametresController::class, 'supprimerLogo'])->name('parametres.logo.supprimer');

        // Mode Production
        Route::get('/parametres/production',  [ProductionController::class, 'index'])->name('parametres.production.index');
        Route::post('/parametres/production', [ProductionController::class, 'appliquer'])->name('parametres.production.appliquer');

        // Gestion du Personnel
        Route::get('/parametres/personnel',                                     [PersonnelController::class, 'index'])->name('parametres.personnel.index');
        Route::post('/parametres/personnel',                                    [PersonnelController::class, 'store'])->name('parametres.personnel.store');
        Route::patch('/parametres/personnel/{utilisateur}',                     [PersonnelController::class, 'update'])->name('parametres.personnel.update');
        Route::delete('/parametres/personnel/{utilisateur}',                    [PersonnelController::class, 'destroy'])->name('parametres.personnel.destroy');
        Route::post('/parametres/personnel/{utilisateur}/reinitialiser',        [PersonnelController::class, 'reinitialiserMotDePasse'])->name('parametres.personnel.reinitialiser');
        Route::post('/parametres/personnel/{utilisateur}/deconnecter-partout',  [PersonnelController::class, 'deconnecterPartout'])->name('parametres.personnel.deconnecter');
        Route::delete('/parametres/personnel/{utilisateur}/avatar',             [PersonnelController::class, 'supprimerAvatar'])->name('parametres.personnel.avatar.supprimer');
        Route::post('/parametres/personnel/{utilisateur}/debloquer',            [PersonnelController::class, 'debloquer'])->name('parametres.personnel.debloquer');

        // Installation (Étape 9)
        Route::get('/parametres/installation',             [InstallationController::class, 'index'])->name('parametres.installation.index');
        Route::get('/parametres/installation/telecharger', [InstallationController::class, 'telecharger'])->name('parametres.installation.telecharger');
        Route::get('/parametres/installation/fichier/{cle}', [InstallationController::class, 'fichier'])->name('parametres.installation.fichier');
        Route::get('/parametres/installation/postes',      [InstallationController::class, 'postes'])->name('parametres.installation.postes');
        Route::post('/parametres/installation/postes/{poste}/renommer', [InstallationController::class, 'renommer'])->name('parametres.installation.renommer');

        // Versions (Étape 10)
        Route::get('/parametres/versions',                     [VersionsController::class, 'index'])->name('parametres.versions.index');
        Route::post('/parametres/versions/televerser',         [VersionsController::class, 'televerser'])->name('parametres.versions.televerser');
        Route::post('/parametres/versions/{version}/pilote',   [VersionsController::class, 'pilote'])->name('parametres.versions.pilote');
        Route::post('/parametres/versions/{version}/general',  [VersionsController::class, 'general'])->name('parametres.versions.general');
        Route::post('/parametres/versions/{version}/depublier',[VersionsController::class, 'depublier'])->name('parametres.versions.depublier');
        Route::delete('/parametres/versions/{version}',        [VersionsController::class, 'supprimer'])->name('parametres.versions.supprimer');
        // Routes ajoutées Étape 10
        Route::get('/parametres/versions/{version}/telecharger', [VersionsController::class, 'telecharger'])->name('parametres.versions.telecharger');
        Route::post('/parametres/versions/forcer-maj',           [VersionsController::class, 'forcerMiseAJour'])->name('parametres.versions.forcer');

        // Routes pour le Parc (Étape 11)
        Route::get('/parametres/parc',                          [ParcController::class, 'index'])->name('parametres.parc.index');
        Route::post('/parametres/parc/{poste}/renommer',        [ParcController::class, 'renommer'])->name('parametres.parc.renommer');
        Route::post('/parametres/parc/{poste}/maintenance',     [ParcController::class, 'maintenance'])->name('parametres.parc.maintenance');
        Route::get('/parametres/parc/{poste}/logs',             [ParcController::class, 'logs'])->name('parametres.parc.logs');
        Route::post('/parametres/parc/{poste}/redemarrer',      [ParcController::class, 'redemarrer'])->name('parametres.parc.redemarrer');
        Route::post('/parametres/parc/restrictions',            [ParcController::class, 'restrictions'])->name('parametres.parc.restrictions');
    });

    // Historique et statistiques
    Route::get('/historique',        [HistoriqueController::class, 'index'])->name('historique.index');
    Route::get('/historique/export', [HistoriqueController::class, 'export'])->name('historique.export');
    Route::get('/statistiques',      [StatistiqueController::class, 'index'])->name('statistiques.index');

    // Profil utilisateur
    Route::get('/profile',            [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',          [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile/avatar',  [ProfileController::class, 'supprimerAvatar'])->name('profile.avatar.supprimer');

    // Route pour "Changer de compte"
    Route::post('/changer-compte', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login')->with('info', 'Vous avez été déconnecté pour changer de compte.');
    })->name('changer-compte');

    // Mon profil
    Route::get('/mon-profil', function () {
        return view('profile.edit', ['user' => auth()->user()]);
    })->name('parametres.profil');

    // MikroTik — statut
    Route::get('/mikrotik/statut', [MikroTikStatusController::class, 'show'])->name('mikrotik.statut');

    // HotSpot
    Route::get('/hotspot', [HotspotController::class, 'index'])->name('hotspot.index');
    Route::get('/hotspot/etat', [HotspotController::class, 'etat'])->name('hotspot.etat');
    Route::get('/hotspot/options-mikrotik', [HotspotController::class, 'optionsMikrotik'])->name('hotspot.options-mikrotik');
    Route::post('/hotspot/generer', [HotspotController::class, 'generer'])->name('hotspot.generer');
    Route::post('/hotspot/ajouter', [HotspotController::class, 'ajouter'])->name('hotspot.ajouter');
    Route::get('/hotspot/imprimer/{lot}', [HotspotController::class, 'imprimerLot'])->name('hotspot.imprimer.lot');
    Route::get('/hotspot/imprimer-disponibles', [HotspotController::class, 'imprimerDisponibles'])->name('hotspot.imprimer.disponibles');
    Route::post('/hotspot/{voucher}/proteger', [HotspotController::class, 'proteger'])->name('hotspot.proteger');
    Route::post('/hotspot/appareils/{appareil}/renommer', [HotspotController::class, 'renommerAppareil'])->name('hotspot.appareils.renommer');
    Route::post('/hotspot/synchroniser-tout', [HotspotController::class, 'synchroniserTout'])->name('hotspot.synchroniser-tout');
    Route::post('/hotspot/supprimer-masse', [HotspotController::class, 'supprimerMasse'])->name('hotspot.supprimer-masse');
    Route::post('/hotspot/pool-mode', [HotspotController::class, 'poolMode'])->name('hotspot.pool-mode');

    // Wi-Fi
    Route::get('/wifi', [WifiController::class, 'index'])->name('wifi.index');
    Route::get('/wifi/etat', [WifiController::class, 'etat'])->name('wifi.etat');

    Route::get('/wifi/comptes', [WifiController::class, 'comptes'])->name('wifi.comptes');
    Route::post('/wifi/sync', [WifiController::class, 'syncNow'])->name('wifi.sync');
    Route::post('/wifi/deconnecter', [WifiController::class, 'deconnecter'])->name('wifi.deconnecter');
    Route::post('/wifi/comptes/{username}/toggle', [WifiController::class, 'toggleCompte'])->name('wifi.comptes.toggle');
    Route::delete('/wifi/comptes/{username}', [WifiController::class, 'supprimerCompte'])->name('wifi.comptes.supprimer');

    Route::post('/wifi/session/{session}/arreter',  [WifiController::class, 'arreter'])->name('wifi.session.arreter');
    Route::post('/wifi/session/{session}/continuer', [WifiController::class, 'continuer'])->name('wifi.session.continuer');
    Route::delete('/wifi/session/{session}',         [WifiController::class, 'supprimer'])->name('wifi.session.supprimer');
    Route::post('/wifi/session/supprimer-masse',     [WifiController::class, 'supprimerMasse'])->name('wifi.session.supprimer-masse');
    Route::post('/wifi/session/{session}/remarque', [WifiController::class, 'enregistrerRemarque'])->name('wifi.session.remarque');

    Route::post('/wifi/session/{session}/alarme',            [WifiController::class, 'definirAlarme'])->name('wifi.session.alarme');
    Route::post('/wifi/session/{session}/alarme/prolonger',  [WifiController::class, 'prolongerAlarme'])->name('wifi.session.alarme.prolonger');
    Route::post('/wifi/session/{session}/alarme/annuler',    [WifiController::class, 'annulerAlarme'])->name('wifi.session.alarme.annuler');
    Route::post('/wifi/session/{session}/alarme/acquitter',  [WifiController::class, 'acquitterAlarme'])->name('wifi.session.alarme.acquitter');
    Route::post('/wifi/session/{session}/alarme/declencher', [WifiController::class, 'marquerAlarmeDeclenchee'])->name('wifi.session.alarme.declencher');

    // Réglages de l'alarme Wi-Fi
    Route::get('/wifi/alarme', [WifiAlarmController::class, 'index'])->name('wifi.alarme.index');
    Route::middleware($admin)->group(function () {
        Route::post('/wifi/alarme/reglages',      [WifiAlarmController::class, 'reglages'])->name('wifi.alarme.reglages');
        Route::post('/wifi/alarme/son',           [WifiAlarmController::class, 'uploader'])->name('wifi.alarme.son');
        Route::post('/wifi/alarme/son/choisir',   [WifiAlarmController::class, 'choisirSon'])->name('wifi.alarme.son.choisir');
        Route::delete('/wifi/alarme/son',         [WifiAlarmController::class, 'supprimerSon'])->name('wifi.alarme.son.supprimer');
        Route::post('/wifi/alarme/reinitialiser', [WifiAlarmController::class, 'reinitialiser'])->name('wifi.alarme.reinitialiser');

        // Portail Captif (Admin)
        Route::get('/admin/portail', [PortailController::class, 'adminIndex'])->name('portail.admin');
        Route::post('/admin/portail', [PortailController::class, 'adminSave'])->name('portail.admin.save');
        Route::post('/admin/portail/reset', [PortailController::class, 'adminResetDefaults'])->name('portail.admin.reset');
        Route::post('/admin/portail/configurer', [PortailController::class, 'configurerRouteur'])->name('portail.admin.configurer');
    });

});

/*
|--------------------------------------------------------------------------
| Portail Captif (Client - Pas de Auth)
|--------------------------------------------------------------------------
*/

Route::get('/portail', [PortailController::class, 'login'])->name('portail.login');
Route::get('/portail/dashboard', [PortailController::class, 'dashboard'])->name('portail.dashboard');
Route::get('/api/portail/stats', [PortailController::class, 'apiStats'])->name('portail.api');

/*
|--------------------------------------------------------------------------
| API pour les Agents Windows
|--------------------------------------------------------------------------
*/

Route::prefix('api/agent')->group(function () {
    Route::post('/handshake', [AgentController::class, 'handshake'])->middleware('throttle:30,1');
    Route::post('/heartbeat', [AgentController::class, 'heartbeat']);
    Route::post('/activer',   [AgentController::class, 'activer']);
    Route::post('/message',   [AgentController::class, 'message']);
    Route::post('/session/suspendre', [AgentController::class, 'suspendreSession']);
    Route::post('/session/reprendre', [AgentController::class, 'reprendreSession']);
    Route::post('/session/fermer',    [AgentController::class, 'fermerSession']);
    Route::get('/commandes',  [AgentController::class, 'commandes']);
    Route::post('/commandes/{commande}/confirmer', [AgentController::class, 'confirmerCommande']);
    Route::get('/logo',       [AgentController::class, 'logo'])->name('agent.logo');
});

// Route pour servir les avatars sans dépendre du dossier public
Route::get('/avatar/{user}', [AvatarController::class, 'show'])->name('avatar.show');

/*
|--------------------------------------------------------------------------
| Authentification (Breeze)
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
require __DIR__.'/agent_maj.php';