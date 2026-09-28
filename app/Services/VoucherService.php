<?php

namespace App\Services;

use App\Models\LotVoucher;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VoucherService
{
    /**
     * Génère un identifiant unique pour un voucher.
     */
    public function genererIdentifiant(
        int $longueur = 8
    ): string {
        do {
            $identifiant = strtoupper(
                Str::random($longueur)
            );
        } while (
            Voucher::where('username', $identifiant)->exists()
            || Voucher::where('code', $identifiant)->exists()
        );

        return $identifiant;
    }

    /**
     * Crée un voucher individuel.
     */
    public function creerVoucher(
        string $username,
        string $password,
        int $montant,
        int $duree,
        string $source = 'manuel',
        ?LotVoucher $lot = null,
        ?string $description = null
    ): Voucher {
        if ($montant < 1) {
            throw new InvalidArgumentException(
                'Le montant doit être supérieur à 0.'
            );
        }

        if ($duree < 1) {
            throw new InvalidArgumentException(
                'La durée doit être supérieure à 0.'
            );
        }

        if (
            Voucher::where('username', $username)->exists()
        ) {
            throw new InvalidArgumentException(
                'Ce username existe déjà.'
            );
        }

        if (
            Voucher::where('code', $password)->exists()
        ) {
            throw new InvalidArgumentException(
                'Ce code existe déjà.'
            );
        }

        return Voucher::create([
            'lot_voucher_id' => $lot?->id,
            'username' => $username,
            'password' => $password,
            'code' => $password,
            'montant' => $montant,
            'duree' => $duree,
            'etat' => 'disponible',
            'source' => $source,
            'date_heure_creation' => now(),
            'description' => $description,
        ]);
    }

    /**
     * Génère un lot de vouchers.
     */
    public function creerLot(
        string $nom,
        int $quantite,
        int $montant,
        int $duree,
        ?string $description = null
    ): LotVoucher {
        if ($quantite < 1) {
            throw new InvalidArgumentException(
                'La quantité doit être supérieure à 0.'
            );
        }

        if ($montant < 1) {
            throw new InvalidArgumentException(
                'Le montant doit être supérieur à 0.'
            );
        }

        if ($duree < 1) {
            throw new InvalidArgumentException(
                'La durée doit être supérieure à 0.'
            );
        }

        return DB::transaction(function () use (
            $nom,
            $quantite,
            $montant,
            $duree,
            $description
        ) {
            $lot = LotVoucher::create([
                'nom' => $nom,
                'quantite_prevue' => $quantite,
                'montant_unitaire' => $montant,
                'duree_unitaire' => $duree,
                'quantite_generee' => 0,
                'source' => 'cyber_manager',
                'description' => $description,
            ]);

            for ($i = 0; $i < $quantite; $i++) {

                $identifiant = $this->genererIdentifiant();

                Voucher::create([
                    'lot_voucher_id' => $lot->id,
                    'username' => $identifiant,
                    'password' => $identifiant,
                    'code' => $identifiant,
                    'montant' => $montant,
                    'duree' => $duree,
                    'etat' => 'disponible',
                    'source' => 'cyber_manager',
                    'date_heure_creation' => now(),
                ]);
            }

            $lot->update([
                'quantite_generee' => $quantite,
            ]);

            return $lot->fresh();
        });
    }

    /**
     * Marque un voucher comme utilisé.
     */
    public function utiliser(
        Voucher $voucher
    ): Voucher {
        if ($voucher->etat !== 'disponible') {
            throw new InvalidArgumentException(
                'Ce voucher n’est pas disponible.'
            );
        }

        $voucher->update([
            'etat' => 'utilise',
            'date_heure_utilisation' => now(),
        ]);

        return $voucher->fresh();
    }

    /**
     * Annule un voucher disponible.
     */
    public function annuler(
        Voucher $voucher
    ): Voucher {
        if ($voucher->etat !== 'disponible') {
            throw new InvalidArgumentException(
                'Seul un voucher disponible peut être annulé.'
            );
        }

        $voucher->update([
            'etat' => 'annule',
        ]);

        return $voucher->fresh();
    }
}