<?php

namespace App\Http\Controllers;

use App\Models\Poste;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatistiqueController extends Controller
{
    /** Fuseau du gérant (la base reste en UTC). */
    private const TZ = 'Indian/Antananarivo';

    private const MODES = [
        'forfait'  => 'Forfait',
        'postpaye' => 'Postpayé',
        'libre'    => 'Débloqué',
    ];

    private const TRANCHES = ['< 30 min', '30 min – 1 h', '1 h – 2 h', '2 h – 4 h', '> 4 h'];

    public function index(Request $request)
    {
        $periode = (string) $request->query('periode', '7');
        if (!in_array($periode, ['7', '30', 'mois'], true)) {
            $periode = '7';
        }

        $now          = Carbon::now(self::TZ);
        $aujourdhui   = $now->copy()->startOfDay();
        $debutMois    = $now->copy()->startOfMonth();
        $debutPeriode = match ($periode) {
            '7'     => $aujourdhui->copy()->subDays(6),
            '30'    => $aujourdhui->copy()->subDays(29),
            default => $debutMois->copy(),
        };
        $debutFetch = $debutPeriode->lt($debutMois) ? $debutPeriode : $debutMois;

        // Une seule requête : postes terminés + Wi-Fi terminés, depuis la date la plus ancienne utile
        $rows = DB::table('cyber_sessions')
            ->select(['id', 'poste_id', 'type_session', 'mode_facturation', 'montant_total',
                      'duree_consommee', 'date_heure_debut', 'date_heure_fin_reelle', 'hotspot_username'])
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('etat', 'terminee')->where('type_session', '!=', 'wifi'))
                  ->orWhere(fn ($w) => $w->where('type_session', 'wifi')
                        ->where(fn ($x) => $x->where('etat', 'terminee')->orWhere('etat_wifi', 'termine')));
            })
            ->whereRaw('COALESCE(date_heure_fin_reelle, date_heure_debut) >= ?',
                [$debutFetch->copy()->setTimezone('UTC')->format('Y-m-d H:i:s')])
            ->get();

        // Structures d'agrégation
        $zero = fn () => ['ca' => 0, 'n' => 0, 'duree' => 0];
        $jour = ['postes' => $zero(), 'wifi' => $zero()];
        $mois = ['postes' => $zero(), 'wifi' => $zero()];

        $serie = [];
        for ($d = $debutPeriode->copy(); $d->lte($aujourdhui); $d->addDay()) {
            $serie[$d->format('Y-m-d')] = ['label' => $d->format('d/m'), 'postes' => 0, 'wifi' => 0];
        }

        $modes = [];
        foreach (self::MODES as $cle => $label) {
            $modes[$cle] = ['label' => $label, 'n' => 0, 'ca' => 0];
        }
        $tranches = [];
        foreach (self::TRANCHES as $label) {
            $tranches[$label] = ['label' => $label, 'n' => 0, 'ca' => 0];
        }
        $topPostes  = [];
        $topComptes = [];

        foreach ($rows as $r) {
            $ref = Carbon::parse($r->date_heure_fin_reelle ?? $r->date_heure_debut, 'UTC')->timezone(self::TZ);

            $source = $r->type_session === 'wifi' ? 'wifi' : 'postes';
            $mode   = $r->mode_facturation ?: 'forfait';
            $duree  = (int) $r->duree_consommee;
            $ca     = ($source === 'postes' && $mode === 'libre') ? 0 : (int) $r->montant_total;

            if ($ref->gte($aujourdhui)) {
                $jour[$source]['ca'] += $ca; $jour[$source]['n']++; $jour[$source]['duree'] += $duree;
            }
            if ($ref->gte($debutMois)) {
                $mois[$source]['ca'] += $ca; $mois[$source]['n']++; $mois[$source]['duree'] += $duree;
            }

            if ($ref->lt($debutPeriode)) {
                continue; // au-delà de la période choisie (cas : période courte, mois long)
            }

            $k = $ref->format('Y-m-d');
            if (isset($serie[$k])) {
                $serie[$k][$source] += $ca;
            }

            if ($source === 'postes') {
                if (isset($modes[$mode])) {
                    $modes[$mode]['n']++; $modes[$mode]['ca'] += $ca;
                }
                $pid = (int) $r->poste_id;
                $topPostes[$pid] ??= ['ca' => 0, 'n' => 0, 'duree' => 0];
                $topPostes[$pid]['ca'] += $ca; $topPostes[$pid]['n']++; $topPostes[$pid]['duree'] += $duree;
            } else {
                $t = match (true) {
                    $duree < 30  => self::TRANCHES[0],
                    $duree < 60  => self::TRANCHES[1],
                    $duree < 120 => self::TRANCHES[2],
                    $duree < 240 => self::TRANCHES[3],
                    default      => self::TRANCHES[4],
                };
                $tranches[$t]['n']++; $tranches[$t]['ca'] += $ca;

                if (filled($r->hotspot_username)) {
                    $topComptes[$r->hotspot_username] ??= ['ca' => 0, 'n' => 0, 'duree' => 0];
                    $topComptes[$r->hotspot_username]['ca'] += $ca;
                    $topComptes[$r->hotspot_username]['n']++;
                    $topComptes[$r->hotspot_username]['duree'] += $duree;
                }
            }
        }

        // Top 5 postes (noms depuis la table postes)
        uasort($topPostes, fn ($a, $b) => [$b['ca'], $b['n']] <=> [$a['ca'], $a['n']]);
        $topPostes = array_slice($topPostes, 0, 5, true);
        $noms = Poste::whereIn('id', array_keys($topPostes))->pluck('nom_poste', 'id');
        $topPostes = collect($topPostes)->map(fn ($v, $id) => $v + [
            'nom'      => $noms[$id] ?? 'Poste supprimé',
            'duree_fmt' => $this->fmtDuree($v['duree']),
        ])->values()->all();

        // Top 5 comptes Wi-Fi
        uasort($topComptes, fn ($a, $b) => [$b['ca'], $b['n']] <=> [$a['ca'], $a['n']]);
        $topComptes = collect(array_slice($topComptes, 0, 5, true))->map(fn ($v, $nom) => $v + [
            'nom'       => $nom,
            'duree_fmt' => $this->fmtDuree($v['duree']),
        ])->values()->all();

        // Cartes de résumé
        $nbMois    = $mois['postes']['n'] + $mois['wifi']['n'];
        $dureeMois = $mois['postes']['duree'] + $mois['wifi']['duree'];

        return view('statistiques.index', [
            'periode'    => $periode,
            'periodes'   => ['7' => '7 jours', '30' => '30 jours', 'mois' => 'Ce mois'],
            'jour'       => $jour,
            'mois'       => $mois,
            'resume'     => [
                'ca_jour'       => $jour['postes']['ca'] + $jour['wifi']['ca'],
                'ca_mois'       => $mois['postes']['ca'] + $mois['wifi']['ca'],
                'n_jour'        => $jour['postes']['n'] + $jour['wifi']['n'],
                'n_mois'        => $nbMois,
                'duree_moyenne' => $nbMois > 0 ? $this->fmtDuree((int) round($dureeMois / $nbMois)) : '—',
                'duree_totale'  => $this->fmtDuree($dureeMois),
            ],
            'serie'      => array_values($serie),
            'modes'      => array_values($modes),
            'tranches'   => array_values($tranches),
            'topPostes'  => $topPostes,
            'topComptes' => $topComptes,
            'libellePeriode' => $periode === 'mois'
                ? 'du ' . $debutMois->format('d/m') . ' au ' . $aujourdhui->format('d/m/Y')
                : 'du ' . $debutPeriode->format('d/m') . ' au ' . $aujourdhui->format('d/m/Y'),
        ]);
    }

    private function fmtDuree(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? "{$h} h" : "{$h} h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . " min";
    }
}