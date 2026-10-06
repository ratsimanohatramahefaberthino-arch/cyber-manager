<?php

namespace App\Services;

use App\Models\Tarif;
use InvalidArgumentException;

class TarifService
{
    // -----------------------------------------------------------------
    // Résolution de la configuration active
    // -----------------------------------------------------------------

    /** Y a-t-il un tarif unifié ou deux tarifs séparés ? */
    public function mode(): string
    {
        return Tarif::where('cible', Tarif::CIBLE_UNIFIE)->exists()
            ? 'unifie'
            : 'separe';
    }

    /** Tarif unique (mode unifié). Null si mode séparé. */
    public function tarifUnifie(): ?Tarif
    {
        return Tarif::where('cible', Tarif::CIBLE_UNIFIE)->first();
    }

    public function tarifEthernet(): ?Tarif
    {
        if ($u = $this->tarifUnifie()) {
            return $u;
        }

        return Tarif::where('cible', Tarif::CIBLE_ETHERNET)->first();
    }

    public function tarifWifi(): ?Tarif
    {
        if ($u = $this->tarifUnifie()) {
            return $u;
        }

        return Tarif::where('cible', Tarif::CIBLE_WIFI)->first();
    }

    /** Compatibilité avec l'ancien code (SessionService). */
    public function tarifStandard(): Tarif
    {
        return $this->tarifPour(Tarif::CIBLE_ETHERNET);
    }

    public function tarifPour(string $cible): Tarif
    {
        if ($cible === Tarif::CIBLE_ETHERNET) {
            $t = $this->tarifEthernet();
        } elseif ($cible === Tarif::CIBLE_WIFI) {
            $t = $this->tarifWifi();
        } else {
            $t = $this->tarifUnifie() ?? $this->tarifEthernet() ?? $this->tarifWifi();
        }

        if (!$t) {
            throw new InvalidArgumentException("Aucun tarif configuré pour « {$cible} ».");
        }

        return $t;
    }

    // -----------------------------------------------------------------
    // Arrondi et minimum
    // -----------------------------------------------------------------

    public function appliquerArrondi(int $montant, Tarif $tarif): int
    {
        if (!$tarif->arrondi_actif || $tarif->unite_arrondi <= 0) {
            return $montant;
        }

        $unite = max(1, (int) $tarif->unite_arrondi);
        $seuil = max(0, min($unite - 1, (int) $tarif->seuil_arrondi));
        $reste = $montant % $unite;

        if ($reste === 0) {
            return $montant;
        }

        return $reste < $seuil
            ? $montant - $reste
            : $montant + ($unite - $reste);
    }

    public function appliquerMinimum(int $montant, Tarif $tarif): int
    {
        if ($tarif->montant_minimum > 0 && $montant < $tarif->montant_minimum) {
            return $tarif->montant_minimum;
        }

        return $montant;
    }

    // -----------------------------------------------------------------
    // Conversions
    // -----------------------------------------------------------------

    public function calculerDuree(int $montant, ?Tarif $tarif = null): int
    {
        $tarif ??= $this->tarifStandard();

        if ($tarif->montant_par_minute <= 0) {
            throw new InvalidArgumentException("Prix/minute invalide.");
        }

        $montant = $this->appliquerMinimum($montant, $tarif);
        $montant = $this->appliquerArrondi($montant, $tarif);

        return intdiv($montant, $tarif->montant_par_minute);
    }

    public function montantPourMinutes(int $minutes, ?Tarif $tarif = null): int
    {
        $tarif ??= $this->tarifStandard();

        $brut = max(0, $minutes) * $tarif->montant_par_minute;

        $brut = $this->appliquerMinimum($brut, $tarif);
        return $this->appliquerArrondi($brut, $tarif);
    }

    // -----------------------------------------------------------------
    // Simulateur
    // -----------------------------------------------------------------

    /**
     * @return array{
     *   entree:int, mode:string, tarif:array, etapes:array, resultat:array
     * }
     */
    public function simuler(int $entree, Tarif $tarif, string $mode = 'montant'): array
    {
        $etapes = [];

        if ($mode === 'montant') {
            $brut = $entree;
            $etapes[] = ['label' => 'Montant saisi', 'valeur' => $this->formatAr($brut)];

            $min = $this->appliquerMinimum($brut, $tarif);
            if ($min !== $brut) {
                $etapes[] = ['label' => 'Minimum appliqué', 'valeur' => $this->formatAr($min)];
                $brut = $min;
            }

            $arr = $this->appliquerArrondi($brut, $tarif);
            if ($arr !== $brut) {
                $etapes[] = ['label' => 'Arrondi appliqué', 'valeur' => $this->formatAr($arr)];
                $brut = $arr;
            }

            $minutes = $tarif->montant_par_minute > 0
                ? intdiv($brut, $tarif->montant_par_minute)
                : 0;

            $etapes[] = [
                'label' => "Durée = {$brut} ÷ {$tarif->montant_par_minute} Ar/min",
                'valeur' => $this->formatDuree($minutes),
            ];

            $resultat = [
                'minutes'  => $minutes,
                'lisible'  => $this->formatDuree($minutes),
                'montant'  => $brut,
            ];
        } else {
            $minutes = max(0, $entree);
            $etapes[] = ['label' => 'Durée saisie', 'valeur' => $this->formatDuree($minutes)];

            $brut = $minutes * $tarif->montant_par_minute;
            $etapes[] = [
                'label' => "Montant brut = {$minutes} × {$tarif->montant_par_minute} Ar",
                'valeur' => $this->formatAr($brut),
            ];

            $min = $this->appliquerMinimum($brut, $tarif);
            if ($min !== $brut) {
                $etapes[] = ['label' => 'Minimum appliqué', 'valeur' => $this->formatAr($min)];
                $brut = $min;
            }

            $arr = $this->appliquerArrondi($brut, $tarif);
            if ($arr !== $brut) {
                $etapes[] = ['label' => 'Arrondi appliqué', 'valeur' => $this->formatAr($arr)];
                $brut = $arr;
            }

            $resultat = [
                'minutes' => $minutes,
                'lisible' => $this->formatDuree($minutes),
                'montant' => $brut,
            ];
        }

        return [
            'entree'   => $entree,
            'mode'     => $mode,
            'tarif'    => [
                'id'                 => $tarif->id,
                'cible'              => $tarif->cible,
                'libelle'            => $tarif->libelle(),
                'montant_par_minute' => $tarif->montant_par_minute,
                'montant_minimum'    => $tarif->montant_minimum,
                'arrondi_actif'      => (bool) $tarif->arrondi_actif,
                'unite_arrondi'      => $tarif->unite_arrondi,
                'seuil_arrondi'      => $tarif->seuil_arrondi,
            ],
            'etapes'   => $etapes,
            'resultat' => $resultat,
        ];
    }

    // -----------------------------------------------------------------

    public function formatAr(int $n): string
    {
        return number_format($n, 0, ',', ' ') . ' Ar';
    }

    public function formatDuree(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $h = intdiv($minutes, 60);
        $r = $minutes % 60;

        return $r === 0
            ? "{$h} h ({$minutes} min)"
            : "{$h} h " . str_pad((string) $r, 2, '0', STR_PAD_LEFT) . " min ({$minutes} min)";
    }

        /**
     * Recalcule les durées des raccourcis d'un tarif selon le prix/minute.
     * Appelé après tout changement de tarif.
     * Le MONTANT est la référence (fixe) ; la DURÉE s'adapte.
     */
    public function resynchroniserRaccourcis(Tarif $tarif): void
    {
        if ($tarif->montant_par_minute <= 0) {
            return;
        }

        foreach ($tarif->raccourcis()->get() as $r) {
            $montant = (int) $r->montant;

            // Applique minimum puis arrondi, puis divise par le prix/minute
            $montantNet = $this->appliquerMinimum($montant, $tarif);
            $montantNet = $this->appliquerArrondi($montantNet, $tarif);

            $nouvelleDuree = intdiv($montantNet, $tarif->montant_par_minute);

            if ($nouvelleDuree !== (int) $r->duree) {
                $r->update(['duree' => $nouvelleDuree]);
            }
        }
    }
}