<?php

namespace App\Services;

use App\Models\Session;
use App\Models\Tarif;

/**
 * Montants Wi-Fi :
 *   - montantTempsReel() : brut (sans arrondi), augmente en continu.
 *   - montantFinal()     : minimum + arrondi appliqués, à l'arrêt.
 */
class WifiMontantService
{
    public function __construct(private TarifService $tarifService) {}

    /** Montant brut affiché pendant la session (pas d'arrondi). */
    public function montantTempsReel(Session $s): int
    {
        $tarif = $this->tarifPour($s);
        if (!$tarif) return 0;

        $secondes = $s->secondesConsommees();
        $minutes  = $secondes / 60;

        return (int) floor($minutes * $tarif->montant_par_minute);
    }

    /** Montant à payer : minimum + arrondi appliqués. */
    public function montantFinal(Session $s): array
    {
        $tarif = $this->tarifPour($s);
        if (!$tarif) {
            return ['brut' => 0, 'final' => 0, 'minutes_reelles' => 0];
        }

        $secondes = $s->secondesConsommees();
        $minutes  = (int) ceil($secondes / 60);

        $brut = $minutes * $tarif->montant_par_minute;
        $brut = $this->tarifService->appliquerMinimum($brut, $tarif);
        $final = $this->tarifService->appliquerArrondi($brut, $tarif);

        return [
            'brut'            => $brut,
            'final'           => $final,
            'minutes_reelles' => $minutes,
        ];
    }

    private function tarifPour(Session $s): ?Tarif
    {
        if ($s->tarif) return $s->tarif;
        try {
            return $this->tarifService->tarifWifi();
        } catch (\Throwable $e) {
            return null;
        }
    }
}