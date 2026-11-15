<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoriqueController extends Controller
{
    /** Fuseau du gérant (la base et Laravel restent en UTC). */
    private const TZ = 'Indian/Antananarivo';

    private const MODES = [
        'forfait'  => 'Forfait',
        'postpaye' => 'Postpayé',
        'libre'    => 'Débloqué',
    ];

    private const MOTIFS = [
        'admin'         => 'Fermée par le gérant',
        'desactivation' => 'Poste désactivé',
        'arret'         => 'Poste arrêté',
    ];

    public function index(Request $request)
    {
        $filtres = $this->filtres($request);
        $base    = $this->requete($filtres);

        // Totaux sur l'ensemble du résultat filtré (pas seulement la page affichée)
        $t = (clone $base)->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(montant_total), 0) as ca, COALESCE(SUM(duree_consommee), 0) as duree')
            ->first();

        $sessions = (clone $base)
            ->with('poste:id,nom_poste')
            ->orderByDesc(DB::raw('COALESCE(date_heure_fin_reelle, date_heure_debut)'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Session $s) => $this->ligne($s));

        return view('historique.index', [
            'sessions' => $sessions,
            'postes'   => Poste::orderBy('nom_poste')->get(['id', 'nom_poste']),
            'filtres'  => $filtres,
            'modes'    => self::MODES,
            'totaux'   => [
                'sessions' => (int) $t->n,
                'ca'       => (int) $t->ca,
                'duree'    => $this->fmtDuree((int) $t->duree),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filtres = $this->filtres($request);
        $base    = $this->requete($filtres)->with('poste:id,nom_poste');
        $nom     = 'historique-sessions-' . Carbon::now(self::TZ)->format('Ymd-Hi') . '.csv';

        return response()->streamDownload(function () use ($base) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : accents corrects dans Excel
            fputcsv($out, ['Date', 'Poste', 'Type', 'Début', 'Fin', 'Durée (min)', 'Montant (Ar)', 'Statut'], ';');

            $base->chunkById(500, function ($lot) use ($out) {
                foreach ($lot as $s) {
                    $l = $this->ligne($s);
                    fputcsv($out, [
                        $l['date'], $l['poste'], $l['type'], $l['debut'], $l['fin'],
                        $l['duree_min'], $l['montant'],
                        $l['statut'] . ($l['motif'] ? ' (' . $l['motif'] . ')' : ''),
                    ], ';');
                }
            });

            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    /** Lit et valide les filtres, puis calcule la plage UTC correspondante. */
    private function filtres(Request $request): array
    {
        $data = $request->validate([
            'periode'  => ['nullable', 'in:tout,aujourdhui,semaine,mois'],
            'poste_id' => ['nullable', 'integer'],
            'mode'     => ['nullable', 'in:forfait,postpaye,libre'],
            'du'       => ['nullable', 'date'],
            'au'       => ['nullable', 'date', 'after_or_equal:du'],
        ]);

        $tz      = self::TZ;
        $periode = $data['periode'] ?? 'tout';
        $du      = $data['du'] ?? null;
        $au      = $data['au'] ?? null;
        $debut   = null;
        $fin     = null;

        if (filled($du) || filled($au)) {
            $periode = 'personnalise';
            $debut   = filled($du) ? Carbon::parse($du, $tz)->startOfDay()->setTimezone('UTC') : null;
            $fin     = filled($au) ? Carbon::parse($au, $tz)->endOfDay()->setTimezone('UTC') : null;
        } else {
            $now = Carbon::now($tz);
            [$debut, $fin] = match ($periode) {
                'aujourdhui' => [$now->copy()->startOfDay()->setTimezone('UTC'), $now->copy()->endOfDay()->setTimezone('UTC')],
                'semaine'    => [$now->copy()->startOfWeek()->setTimezone('UTC'), $now->copy()->endOfWeek()->setTimezone('UTC')],
                'mois'       => [$now->copy()->startOfMonth()->setTimezone('UTC'), $now->copy()->endOfMonth()->setTimezone('UTC')],
                default      => [null, null],
            };
        }

        return [
            'periode'  => $periode,
            'poste_id' => $data['poste_id'] ?? null,
            'mode'     => $data['mode'] ?? null,
            'du'       => $du,
            'au'       => $au,
            'debut'    => $debut,
            'fin'      => $fin,
        ];
    }

    /** Requête commune à la liste, aux totaux et à l'export (sans tri). */
    private function requete(array $f): Builder
    {
        $q = Session::query()
            ->where('etat', 'terminee')
            ->where('type_session', '!=', 'wifi');

        $dateRef = DB::raw('COALESCE(date_heure_fin_reelle, date_heure_debut)');

        if ($f['debut']) $q->where($dateRef, '>=', $f['debut']);
        if ($f['fin'])   $q->where($dateRef, '<=', $f['fin']);
        if ($f['poste_id']) $q->where('poste_id', $f['poste_id']);
        if ($f['mode']) {
            // Anciennes sessions sans mode = forfait (valeur par défaut de la colonne)
            $f['mode'] === 'forfait'
                ? $q->where(fn ($w) => $w->where('mode_facturation', 'forfait')->orWhereNull('mode_facturation'))
                : $q->where('mode_facturation', $f['mode']);
        }

        return $q;
    }

    /** Transforme une session en ligne d'affichage (dates converties en heure locale). */
    private function ligne(Session $s): array
    {
        $mode  = $s->mode_facturation ?: 'forfait';
        $debut = $s->date_heure_debut?->copy()->timezone(self::TZ);
        $fin   = $s->date_heure_fin_reelle?->copy()->timezone(self::TZ);
        $motif = filled($s->motif_fin)
            ? (self::MOTIFS[$s->motif_fin] ?? ucfirst(str_replace('_', ' ', $s->motif_fin)))
            : null;

        return [
            'id'        => $s->id,
            'date'      => ($fin ?? $debut)?->format('d/m/Y') ?? '—',
            'debut'     => $debut?->format('H:i') ?? '—',
            'fin'       => $fin?->format('H:i') ?? '—',
            'poste'     => $s->poste?->nom_poste ?? 'Poste supprimé',
            'mode'      => $mode,
            'type'      => self::MODES[$mode] ?? $mode,
            'duree_min' => (int) $s->duree_consommee,
            'duree'     => $this->fmtDuree((int) $s->duree_consommee),
            'montant'   => $mode === 'libre' ? 0 : (int) $s->montant_total,
            'statut'    => 'Terminée',
            'motif'     => $motif,
        ];
    }

    private function fmtDuree(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) return "{$minutes} min";

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? "{$h} h" : "{$h} h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . " min";
    }
}