<?php

namespace App\Console\Commands;

use App\Models\Session;
use App\Services\PosteSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Maintenance des postes clients.
 *
 *  postes:maintenance           → expire les forfaits terminés + clôture les sessions de postes
 *                                  injoignables au-delà de la tolérance (voir config/postes.php)
 *  postes:maintenance --reseau  → réconcilie MikroTik avec l'état réel des sessions
 *
 * Les verrous utilisent le cache « file » : aucune dépendance à la table cache de la base.
 */
class MaintenancePostes extends Command
{
    protected $signature = 'postes:maintenance {--reseau : Réconcilie l\'état réseau MikroTik (connecté / coupé)}';

    protected $description = 'Expire les forfaits, clôture les sessions de postes injoignables, réconcilie MikroTik.';

    public function handle(PosteSessionService $sessions): int
    {
        $reseau = (bool) $this->option('reseau');

        // Un seul exemplaire à la fois (évite les doubles clôtures si un passage traîne)
        $verrou = Cache::store('file')->lock($reseau ? 'postes:maintenance:reseau' : 'postes:maintenance', 120);
        if (!$verrou->get()) {
            return self::SUCCESS;
        }

        try {
            if ($reseau) {
                $this->reconcilierReseau($sessions);
            } else {
                $this->expirerLesForfaits($sessions);
                $this->cloturerLesPostesInjoignables($sessions);
            }
        } catch (\Throwable $e) {
            Log::error('Maintenance des postes : erreur inattendue : ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            $verrou->release();
        }

        return self::SUCCESS;
    }

    /** Forfaits dont le temps est écoulé (postes en ligne ou non). */
    private function expirerLesForfaits(PosteSessionService $sessions): void
    {
        $candidates = Session::query()
            ->where('etat', 'en_cours')
            ->where('mode_facturation', 'forfait')
            ->where('duree_prevue', '>', 0)
            ->whereNotNull('poste_id')
            ->with('poste')
            ->get();

        foreach ($candidates as $session) {
            $poste = $session->poste;
            if ($poste === null || !$sessions->sessionExpiree($session)) {
                continue;
            }
            $sessions->expirerSiNecessaire($poste);
        }
    }

    /** Sessions « en cours » dont le poste ne donne plus signe de vie depuis plus que la tolérance. */
    private function cloturerLesPostesInjoignables(PosteSessionService $sessions): void
    {
        $magasin    = Cache::store('file');
        $maintenant = now()->getTimestamp();

        // Garde de redémarrage : si le planificateur était arrêté, on laisse les agents se reconnecter
        $dernierPassage = $magasin->get('postes:maintenance:dernier_passage');
        $magasin->put('postes:maintenance:dernier_passage', $maintenant, 86400);
        if ($dernierPassage === null || $maintenant - (int) $dernierPassage > 60) {
            $garde = (int) config('postes.garde_redemarrage_secondes', 90);
            $magasin->put('postes:maintenance:garde_jusqua', $maintenant + $garde, 86400);
            Log::info("Maintenance des postes (re)démarrée : pas de clôture pour injoignabilité pendant {$garde} s.");
        }
        if ((int) $magasin->get('postes:maintenance:garde_jusqua', 0) > $maintenant) {
            return;
        }

        $tolerance = (int) config('postes.hors_ligne_tolerance_minutes', 10);
        if ($tolerance <= 0) {
            return;   // clôture automatique désactivée : le gérant ferme à la main
        }

        $sessionsEnCours = Session::query()
            ->where('etat', 'en_cours')
            ->whereNotNull('poste_id')
            ->with('poste')
            ->get();

        foreach ($sessionsEnCours as $session) {
            $poste = $session->poste;
            if ($poste === null || $poste->estEnLigne()) {
                continue;
            }
            if ($poste->dernier_heartbeat && $poste->dernier_heartbeat->gt(now()->subMinutes($tolerance))) {
                continue;   // encore dans la tolérance
            }
            $sessions->cloturerSiInjoignable($poste, $tolerance);
        }
    }

    private function reconcilierReseau(PosteSessionService $sessions): void
    {
        $bilan = $sessions->reconcilierReseau();

        if ($bilan['connectes'] > 0 || $bilan['coupes'] > 0) {
            Log::info("Réconciliation MikroTik : {$bilan['connectes']} poste(s) reconnecté(s), {$bilan['coupes']} coupé(s).");
        }
    }
}