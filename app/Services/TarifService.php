<?php

namespace App\Services;

use App\Models\Tarif;
use InvalidArgumentException;

class TarifService
{
    public function tarifStandard(): Tarif
    {
        $tarif = Tarif::where('actif', true)
            ->orderBy('id')
            ->first();

        if (!$tarif) {
            throw new InvalidArgumentException(
                'Aucun tarif actif n’est configuré.'
            );
        }

        return $tarif;
    }

    public function calculerDuree(
        int $montant,
        ?Tarif $tarif = null
    ): int {
        $tarif ??= $this->tarifStandard();

        if ($montant < $tarif->montant_minimum) {
            throw new InvalidArgumentException(
                "Le montant minimum est de {$tarif->montant_minimum} Ar."
            );
        }

        return intdiv(
            $montant,
            $tarif->montant_par_minute
        );
    }

    public function montantPourMinutes(
        int $minutes,
        ?Tarif $tarif = null
    ): int {
        $tarif ??= $this->tarifStandard();

        return $minutes * $tarif->montant_par_minute;
    }
}