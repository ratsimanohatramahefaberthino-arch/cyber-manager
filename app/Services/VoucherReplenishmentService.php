<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Maintient un stock d'identifiants usage unique disponibles (le "pool").
 * Ne compte, ne crée et ne touche que des identifiants usage unique non
 * protégés ; ne supprime jamais rien ; ne fait rien si MikroTik est injoignable.
 */
class VoucherReplenishmentService
{
    private const VERROU = 'vouchers:reapprovisionnement';

    public function __construct(
        private VoucherService $vouchers,
        private MikroTikService $mikroTik,
    ) {
    }

       public function verifierEtReapprovisionner(bool $simuler = false, bool $force = false): array
    {
        $verrou = null;

        if (!$simuler) {
            $verrou = Cache::lock(self::VERROU, 600);

            if (!$verrou->get()) {
                return $this->resultat('ignore', 'Un réapprovisionnement est déjà en cours.');
            }
        }

        try {
            return $this->executer($simuler, $force);
        } finally {
            $verrou?->release();
        }
    }

     private function executer(bool $simuler, bool $force = false): array
    {
        $mode = \App\Models\Parametre::get('hotspot_pool_mode', 'auto');

        if ($mode === 'manuel' && !$force) {
            return $this->resultat('ignore', 'Mode manuel actif : réapprovisionnement automatique désactivé.');
        }

        $seuil       = max(0, (int) config('mikrotik.voucher_pool_seuil', 10));
        $taille      = max(0, (int) config('mikrotik.voucher_pool_taille', 40));
        $disponibles = $this->compterDisponibles();

        if ($disponibles >= $seuil) {
            return $this->resultat('ok', "Pool suffisant : {$disponibles} disponible(s) pour un seuil de {$seuil}.");
        }

        $aGenerer = $taille - $disponibles;

        if ($aGenerer < 1) {
            return $this->resultat('ignore', 'Configuration incohérente : la taille du pool doit dépasser le seuil.');
        }

        if ($simuler) {
            return $this->resultat('simulation', "Générerait {$aGenerer} identifiant(s).", ['a_generer' => $aGenerer]);
        }

        try {
            $this->mikroTik->testConnection();
        } catch (\Throwable $e) {
            Log::warning('Réapprovisionnement reporté : MikroTik injoignable', ['error' => $e->getMessage()]);
            return $this->resultat('ignore', 'MikroTik injoignable, réapprovisionnement reporté.');
        }

        try {
            $lot = $this->vouchers->genererEnMasse([
                'quantite' => $aGenerer,
                'mode'     => 'distinct',
                'longueur' => 4,
                'jeu'      => 'minuscules',
            ]);
        } catch (\Throwable $e) {
            Log::error('Réapprovisionnement des identifiants échoué', ['error' => $e->getMessage()]);
            return $this->resultat('erreur', $e->getMessage());
        }

        $nonSync = Voucher::where('lot_voucher_id', $lot->id)->whereNull('mikrotik_synced_at')->count();

        $message = "{$lot->quantite_generee} identifiant(s) généré(s).";
        if ($nonSync > 0) {
            $message .= " {$nonSync} non synchronisé(s) avec MikroTik.";
        }

        return $this->resultat('genere', $message, [
            'generes'          => (int) $lot->quantite_generee,
            'lot_id'           => $lot->id,
            'non_synchronises' => $nonSync,
        ]);
    }
    
    private function compterDisponibles(): int
    {
        return Voucher::porteeTemporaires()->porteeDisponibles()->count();
    }

    private function resultat(string $statut, string $message, array $extra = []): array
    {
        return array_merge([
            'statut'           => $statut,
            'message'          => $message,
            'seuil'            => max(0, (int) config('mikrotik.voucher_pool_seuil', 10)),
            'taille'           => max(0, (int) config('mikrotik.voucher_pool_taille', 40)),
            'disponibles'      => $this->compterDisponibles(),
            'a_generer'        => 0,
            'generes'          => 0,
            'lot_id'           => null,
            'non_synchronises' => 0,
        ], $extra);
    }
}