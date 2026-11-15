<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\PosteCommande;
use App\Models\PosteMessage;
use App\Models\Session;
use App\Models\TarifRaccourci;
use App\Services\PosteSessionService;
use App\Services\TarifService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PosteController extends Controller
{
    private const ACTIONS = [
        'demarrer'    => ['label' => 'Démarrer',     'couleur' => 'vert',  'modal' => true],
        'debloquer'   => ['label' => 'Débloquer',    'couleur' => 'bleu'],
        'fermer'      => ['label' => 'Fermer',       'couleur' => 'rouge', 'confirm' => 'Fermer la session ? Le montant sera calculé.'],
        'suspendre'   => ['label' => 'Suspendre',    'couleur' => 'ambre', 'confirm' => 'Suspendre la session ?'],
        'reprendre'   => ['label' => 'Reprendre',    'couleur' => 'vert'],
        'verrouiller' => ['label' => 'Verrouiller',  'couleur' => 'gris',  'confirm' => 'Verrouiller le poste ?'],
        'recharger'   => ['label' => 'Recharger',    'couleur' => 'ambre', 'modal' => true],
        'desactiver'  => ['label' => 'Désactiver',   'couleur' => 'gris',  'confirm' => 'Désactiver ce poste ?'],
        'activer'     => ['label' => 'Activer',      'couleur' => 'vert'],
        'arreter'     => ['label' => 'Arrêter',      'couleur' => 'rouge', 'confirm' => "Éteindre le PC ?"],
    ];

    public function __construct(private PosteSessionService $sessions) {}

    public function index()
    {
        $tarif = app(TarifService::class)->tarifStandard();

        return view('postes.index', [
            'postes' => $this->tousLesPostes(),
            'tarifs' => $tarif->raccourcis()->get(),
            'tarif_minute' => $tarif->montant_par_minute,
            'tarif_min' => $tarif->montant_minimum,
            'tarif_arrondi' => [
                'actif' => (bool) $tarif->arrondi_actif,
                'unite' => (int) $tarif->unite_arrondi,
                'seuil' => (int) $tarif->seuil_arrondi,
            ],
        ]);
    }

    public function etat(): JsonResponse
    {
        return response()->json(['ok' => true, 'postes' => $this->tousLesPostes()]);
    }

    public function chat(Request $request, Poste $poste): JsonResponse
    {
        $apres = (int) $request->query('apres', 0);
        $messages = $poste->messages()->where('id', '>', $apres)->orderBy('id')->limit(100)->get(['id', 'sens', 'contenu', 'created_at', 'lu_at']);

        $nonLus = $messages->where('sens', PosteMessage::SENS_CLIENT)->whereNull('lu_at')->pluck('id');
        if ($nonLus->isNotEmpty()) PosteMessage::whereIn('id', $nonLus)->update(['lu_at' => now()]);

        return response()->json([
            'ok'       => true,
            'messages' => $messages->map(fn ($m) => ['id' => $m->id, 'sens' => $m->sens, 'contenu' => $m->contenu, 'heure' => $m->created_at?->format('H:i')])->values(),
        ]);
    }

    public function demarrer(Request $request, Poste $poste)
    {
        $data = $request->validate([
            'type'          => ['required', 'in:forfait,postpaye'],
            'duree_minutes' => ['required_if:type,forfait', 'nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        if ($poste->etatAffiche() !== Poste::ETAT_DISPONIBLE && $poste->etatAffiche() !== Poste::ETAT_DESACTIVE) {
            return $this->echec($request, "Le poste n'est pas disponible.");
        }

        // Première activation d'un forfait : le minimum du tarif s'applique (pas aux recharges).
        if ($data['type'] === 'forfait') {
            try {
                $tarif   = app(TarifService::class)->tarifStandard();
                $pm      = max(1, (int) $tarif->montant_par_minute);
                $minimum = (int) $tarif->montant_minimum;
                $dureeMin = (int) ceil($minimum / $pm);

                if ($minimum > 0 && (int) $data['duree_minutes'] < $dureeMin) {
                    return $this->echec($request, "Première activation : minimum {$minimum} Ar ({$dureeMin} min).");
                }
            } catch (\Throwable) {
                // tarif introuvable : on ne bloque pas
            }
        }

        $duree = $data['type'] === 'forfait' ? (int) $data['duree_minutes'] : null;
        $session = $this->sessions->creerSession($poste, $data['type'], $duree);
        $reseau  = $this->sessions->connecterReseau($poste);

        PosteMessage::where('poste_id', $poste->id)->delete();

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'demarrer', 'payload' => ['session_id' => $session->id, 'duree_minutes' => $duree, 'type' => $data['type']]]);
        $poste->update(['etat' => Poste::ETAT_OCCUPE]);

        return $this->ok($request, "Poste {$poste->nom_poste} démarré." . $this->avertissementReseau($reseau));
    }

    public function debloquer(Request $request, Poste $poste)
    {
        if (!$poste->sessionActive()) $this->sessions->creerSession($poste, 'libre', null);
        $this->sessions->reprendre($poste);
        $reseau = $this->sessions->connecterReseau($poste);
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'debloquer']);
        return $this->ok($request, "Poste {$poste->nom_poste} débloqué." . $this->avertissementReseau($reseau));
    }

    public function fermer(Request $request, Poste $poste)
    {
        $session = $this->terminer($poste, 'admin');
        if ($session === null) return $this->echec($request, 'Aucune session active à fermer.');

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);
        $poste->update(['etat' => Poste::ETAT_DESACTIVE]);

        $montant = ($session->mode_facturation ?? 'forfait') === 'libre' ? '' : ' Montant : ' . number_format((int) $session->montant_total, 0, ',', ' ') . ' Ar.';
        return $this->ok($request, "Session de {$poste->nom_poste} fermée.{$montant}");
    }

    public function suspendre(Request $request, Poste $poste)
    {
        $ok = DB::transaction(function () use ($poste) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            return $p !== null && $this->sessions->suspendre($p);
        });

        if (!$ok) return $this->echec($request, 'Aucune session active.');
        $reseau = $this->sessions->deconnecterReseau($poste);
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'suspendre']);
        return $this->ok($request, "Session suspendue." . $this->avertissementReseau($reseau, 'coupé'));
    }

    public function reprendre(Request $request, Poste $poste)
    {
        if (!$this->sessions->reprendre($poste)) return $this->echec($request, 'Aucune session à reprendre.');
        $reseau = $this->sessions->connecterReseau($poste);
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'reprendre']);
        return $this->ok($request, "Session reprise." . $this->avertissementReseau($reseau));
    }

    public function verrouiller(Request $request, Poste $poste)
    {
        if (!$poste->sessionActive()) return $this->echec($request, 'Aucune session active.');
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);
        $poste->update(['etat' => Poste::ETAT_VERROUILLE]);
        return $this->ok($request, "Poste verrouillé.");
    }

    public function desactiver(Request $request, Poste $poste)
    {
        if ($poste->etatAffiche() === Poste::ETAT_OCCUPE) return $this->echec($request, "Impossible de désactiver un poste en cours d'utilisation.");
        $this->terminer($poste, 'desactivation');
        PosteMessage::where('poste_id', $poste->id)->delete();
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'desactiver']);
        $poste->update(['etat' => Poste::ETAT_DESACTIVE]);
        return $this->ok($request, "Poste désactivé.");
    }

    public function activer(Request $request, Poste $poste)
    {
        if ($poste->etat === Poste::ETAT_DESACTIVE) {
            $poste->update(['etat' => Poste::ETAT_DISPONIBLE]);
            PosteMessage::where('poste_id', $poste->id)->delete();   // nouveau client : on repart d'un chat vide
            PosteCommande::create(['poste_id' => $poste->id, 'type' => 'verrouiller']);
            return $this->ok($request, "Poste activé.");
        }
        return $this->echec($request, "Le poste n'est pas désactivé.");
    }

    public function arreter(Request $request, Poste $poste)
    {
        $this->terminer($poste, 'arret');
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'arreter']);
        $poste->update(['etat' => Poste::ETAT_DESACTIVE]);
        return $this->ok($request, "Commande d'arrêt envoyée.");
    }

    public function recharger(Request $request, Poste $poste)
    {
        $data = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:1440']]);

        $resultat = DB::transaction(function () use ($poste, $data) {
            $p       = Poste::whereKey($poste->id)->lockForUpdate()->first();
            $session = $p?->sessionActive();

            if ($session === null) return 'aucune';
            if (($session->mode_facturation ?? 'forfait') !== 'forfait') return 'non_forfait';

            $nouvelleDuree = (int) $session->duree_prevue + $data['minutes'];
            $session->update([
                'date_heure_fin_prevue' => $session->date_heure_fin_prevue->copy()->addMinutes($data['minutes']),
                'duree_prevue' => $nouvelleDuree,
            ]);
            return $nouvelleDuree;
        });

        if ($resultat === 'aucune') return $this->echec($request, 'Aucune session active.');
        if ($resultat === 'non_forfait') return $this->echec($request, "Seul un forfait peut être rechargé.");

        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'recharger', 'payload' => ['minutes_ajoutees' => (int) $data['minutes']]]);
        return $this->ok($request, "{$data['minutes']} min ajoutées (forfait : {$resultat} min).");
    }

    public function envoyerMessage(Request $request, Poste $poste)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);
        $m = PosteMessage::create(['poste_id' => $poste->id, 'sens' => PosteMessage::SENS_ADMIN, 'contenu' => $data['message']]);
        PosteCommande::create(['poste_id' => $poste->id, 'type' => 'message', 'payload' => ['message' => $data['message']]]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Message envoyé.',
                'id'      => $m->id,
                'heure'   => $m->created_at?->format('H:i'),
            ]);
        }
        return back()->with('success', 'Message envoyé.');
    }

    // ------------------------------------------------------------------
    // Recharges rapides (persistées côté serveur : table tarif_raccourcis)
    // ------------------------------------------------------------------

    public function ajouterRaccourci(Request $request): JsonResponse
    {
        $data = $request->validate(['montant' => ['required', 'integer', 'min:1', 'max:10000000']]);

        try {
            $tarif = app(TarifService::class)->tarifStandard();
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'message' => 'Aucun tarif configuré.'], 422);
        }

        $montant = (int) $data['montant'];
        $pm      = (int) $tarif->montant_par_minute;

        if ($pm <= 0) return response()->json(['ok' => false, 'message' => 'Prix par minute invalide.'], 422);
        if ($tarif->montant_minimum > 0 && $montant < $tarif->montant_minimum) {
            return response()->json(['ok' => false, 'message' => "Montant trop faible : le minimum est de {$tarif->montant_minimum} Ar."], 422);
        }

        $duree = (int) round($montant / $pm);   // même calcul que l'interface
        if ($duree < 1) return response()->json(['ok' => false, 'message' => 'Durée invalide.'], 422);

        $existe = $tarif->raccourcis()
            ->where(fn ($q) => $q->where('montant', $montant)->orWhere('duree', $duree))
            ->exists();
        if ($existe) {
            return response()->json(['ok' => false, 'message' => 'Une recharge identique (même montant ou même durée) existe déjà dans le tableau.'], 422);
        }

        $r = new TarifRaccourci();
        $r->forceFill(['tarif_id' => $tarif->id, 'montant' => $montant, 'duree' => $duree, 'ordre' => 0])->save();

        return response()->json(['ok' => true, 'id' => $r->id, 'montant' => $montant, 'duree' => $duree]);
    }

    public function supprimerRaccourci(Request $request, int $id): JsonResponse
    {
        $r = TarifRaccourci::find($id);
        if ($r === null) return response()->json(['ok' => true]);   // déjà supprimée : rien à faire

        $r->delete();
        return response()->json(['ok' => true]);
    }

    private function tousLesPostes(): array
    {
        return Poste::orderBy('nom_poste')
            ->withCount(['messages as non_lus' => fn ($q) => $q->where('sens', PosteMessage::SENS_CLIENT)->whereNull('lu_at')])
            ->get()->map(fn (Poste $p) => $this->donnees($p))->values()->all();
    }

    private function donnees(Poste $p): array
    {
        $etat = $p->etatAffiche();
        $s    = $p->sessionActive();
        if (!$s && $etat === Poste::ETAT_DESACTIVE) $s = $p->sessions()->latest()->first();

        $session = null;
        $estChatActif = $etat === Poste::ETAT_OCCUPE || ($etat === Poste::ETAT_DESACTIVE && $s !== null);

        if ($s) {
            $mode    = $s->mode_facturation ?: 'forfait';
            $ecoule  = (int) $s->secondesConsommees();
            $prevue  = (int) $s->duree_prevue;
            $restant = ($mode === 'forfait' && $prevue > 0) ? max(0, $prevue * 60 - $ecoule) : null;

            $forfaitTotal = 0;
            if ($mode === 'forfait') {
                $tarif = app(\App\Services\TarifService::class)->tarifStandard();
                $forfaitTotal = app(\App\Services\TarifService::class)->montantPourMinutes($prevue, $tarif);
            }

            $session = [
                'id'         => $s->id,
                'mode'       => $mode,
                'etat'       => $s->etat,
                'debut'      => $s->date_heure_debut?->format('H:i:s'),
                'debut_iso'  => $s->date_heure_debut?->toIso8601String(),   // horodatage absolu : affiché à l'heure réelle côté navigateur
                'ecoule_s'   => $ecoule,
                'restant_s'  => $restant,
                'duree_min'  => $prevue ?: null,
                'forfait_total' => $forfaitTotal,
                'montant'    => $mode === 'libre' ? 0 : ($s->etat === 'terminee' ? (int) $s->montant_total : $this->sessions->montant($s, $ecoule)),
            ];
        }

        return [
            'id'       => $p->id,
            'nom'      => $p->nom_poste,
            'etat'     => $etat,
            'ip'       => $p->adresse_ip,
            'mac'      => $p->adresse_mac,
            'version'  => $p->agent_version,
            'session'  => $session,
            'non_lus'  => $estChatActif ? (int) ($p->non_lus ?? 0) : 0,
            'hotspot_username'  => $p->hotspot_username,
            'hotspot_configure' => filled($p->hotspot_username) && filled($p->hotspot_password),
            'actions'  => $this->actionsPour($etat, $s !== null, $session['mode'] ?? null),
        ];
    }

    private function actionsPour(string $etat, bool $aSession, ?string $modeSession = null): array
    {
        $cles = match ($etat) {
            Poste::ETAT_DISPONIBLE => ['demarrer', 'debloquer', 'desactiver', 'arreter'],
            Poste::ETAT_OCCUPE     => ['fermer', 'suspendre', 'verrouiller', 'recharger', 'arreter'],
            Poste::ETAT_SUSPENDU   => ['reprendre', 'fermer', 'desactiver', 'arreter'],
            Poste::ETAT_VERROUILLE => $aSession ? ['debloquer', 'fermer', 'recharger', 'desactiver', 'arreter'] : ['debloquer', 'desactiver', 'arreter'],
            Poste::ETAT_HORS_LIGNE => ['desactiver'],
            Poste::ETAT_DESACTIVE  => ['activer'],
            default                => [],
        };

        if ($modeSession !== 'forfait') $cles = array_values(array_diff($cles, ['recharger']));
        return array_map(fn (string $cle) => ['cle' => $cle] + self::ACTIONS[$cle], $cles);
    }

    private function terminer(Poste $poste, string $motif): ?Session
    {
        $arret = $poste->estEnLigne() ? null : $poste->dernier_heartbeat;
        $session = DB::transaction(function () use ($poste, $motif, $arret) {
            $p = Poste::whereKey($poste->id)->lockForUpdate()->first();
            return $p !== null ? $this->sessions->cloturer($p, $motif, $arret) : null;
        });
        $this->sessions->deconnecterReseau($poste);
        return $session;
    }

    private function avertissementReseau(?bool $reseau, string $verbe = 'activé'): string
    {
        return $reseau === false ? " Attention : Internet n'a pas pu être {$verbe}." : '';
    }

    private function ok(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message]) : back()->with('success', $message);
    }

    private function echec(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $message], 422) : back()->with('error', $message);
    }
}