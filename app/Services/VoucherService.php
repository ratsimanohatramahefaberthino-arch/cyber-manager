<?php

namespace App\Services;

use App\Models\LotVoucher;
use App\Models\Parametre;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VoucherService
{
    public function __construct(
        private MikroTikService $mikroTik,
    ) {
    }

    // -----------------------------------------------------------------
    // Génération en masse (façon Mikhmon : quantité + paramètres techniques)
    // -----------------------------------------------------------------

    /**
     * @param array{
     *   quantite:int, mode:string, longueur:int, prefixe?:string, jeu:string,
     *   serveur?:string, profil?:string, duree?:int, limite_data_mo?:int, commentaire?:string
     * } $p
     */
    public function genererEnMasse(array $p): LotVoucher
    {
        $quantite = (int) $p['quantite'];
        if ($quantite < 1 || $quantite > 200) {
            throw new InvalidArgumentException('La quantité doit être comprise entre 1 et 200.');
        }

        $mode     = $p['mode'] ?? 'distinct';
        $longueur = max(3, min(8, (int) ($p['longueur'] ?? 4)));
        $prefixe  = preg_replace('/\s+/', '', trim((string) ($p['prefixe'] ?? '')));
        $jeu      = $this->jeuCaracteres($p['jeu'] ?? 'minuscules');
        $serveur  = trim((string) ($p['serveur'] ?? '')) ?: null;
        $profil   = trim((string) ($p['profil'] ?? '')) ?: null;
        $duree    = (int) ($p['duree'] ?? 0);
        $dataMo   = isset($p['limite_data_mo']) && $p['limite_data_mo'] !== '' ? (int) $p['limite_data_mo'] : null;
        $commentaire = trim((string) ($p['commentaire'] ?? '')) ?: null;

        $lot = DB::transaction(function () use (
            $quantite, $mode, $longueur, $prefixe, $jeu, $serveur, $profil, $duree, $dataMo, $commentaire
        ) {
            $lot = LotVoucher::create([
                'nom'              => 'Génération du ' . now()->format('d/m/Y H:i'),
                'quantite_prevue'  => $quantite,
                'montant_unitaire' => 0,
                'duree_unitaire'   => $duree,
                'quantite_generee' => 0,
                'source'           => 'cyber_manager',
            ]);

            for ($i = 0; $i < $quantite; $i++) {
                do {
                    $username = $prefixe . $this->genererChaine($longueur, $jeu);
                } while (Voucher::where('username', $username)->exists());

                $password = $mode === 'identique' ? $username : $this->genererChaine($longueur, $jeu);

                Voucher::create([
                    'lot_voucher_id'      => $lot->id,
                    'username'            => $username,
                    'password'            => $password,
                    'code'                => $username,
                    'montant'             => 0,
                    'duree'               => $duree,
                    'etat'                => 'disponible',
                    'source'              => 'cyber_manager',
                    'type_voucher'        => Voucher::TYPE_TEMPORAIRE,
                    'protege'             => false,
                    'serveur'             => $serveur,
                    'profil'              => $profil,
                    'limite_data_mo'      => $dataMo,
                    'description'         => $commentaire,
                    'date_heure_creation' => now(),
                ]);
            }

            $lot->update(['quantite_generee' => $quantite]);

            return $lot->fresh();
        });

        foreach (Voucher::where('lot_voucher_id', $lot->id)->get() as $voucher) {
            $this->synchroniserVersMikroTik($voucher);
        }

        return $lot;
    }

    private function jeuCaracteres(string $cle): string
    {
        return match ($cle) {
            'majuscules'          => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'mixte'                => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'minuscules_chiffres'  => 'abcdefghijklmnopqrstuvwxyz0123456789',
            'majuscules_chiffres'  => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'mixte_chiffres'       => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            default                => 'abcdefghijklmnopqrstuvwxyz',
        };
    }

    private function genererChaine(int $longueur, string $car): string
    {
        $chaine = '';
        for ($i = 0; $i < $longueur; $i++) {
            $chaine .= $car[random_int(0, strlen($car) - 1)];
        }
        return $chaine;
    }

    // -----------------------------------------------------------------
    // Ajout manuel
    // -----------------------------------------------------------------

    /**
     * @param array{
     *   username:string, password:string, nom?:string, reutilisable?:bool, protege?:bool,
     *   serveur?:string, profil?:string, duree?:int, limite_data_mo?:int, commentaire?:string
     * } $p
     */
    public function creerIdentifiantManuel(array $p): Voucher
    {
        $username = trim((string) $p['username']);
        $password = trim((string) $p['password']);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Le username et le mot de passe sont obligatoires.');
        }
        if (Voucher::where('username', $username)->exists()) {
            throw new InvalidArgumentException('Ce username existe déjà.');
        }

        $voucher = Voucher::create([
            'username'            => $username,
            'nom'                 => $p['nom'] ?? null,
            'password'            => $password,
            'code'                => $username,
            'montant'             => 0,
            'duree'               => (int) ($p['duree'] ?? 0),
            'etat'                => 'disponible',
            'source'              => 'manuel',
            'type_voucher'        => !empty($p['reutilisable']) ? Voucher::TYPE_PERMANENT : Voucher::TYPE_TEMPORAIRE,
            'protege'             => !empty($p['protege']),
            'serveur'             => trim((string) ($p['serveur'] ?? '')) ?: null,
            'profil'              => trim((string) ($p['profil'] ?? '')) ?: null,
            'limite_data_mo'      => isset($p['limite_data_mo']) && $p['limite_data_mo'] !== '' ? (int) $p['limite_data_mo'] : null,
            'description'         => trim((string) ($p['commentaire'] ?? '')) ?: null,
            'date_heure_creation' => now(),
        ]);

        $this->synchroniserVersMikroTik($voucher);

        return $voucher->fresh();
    }

    // -----------------------------------------------------------------
    // Synchronisation MikroTik
    // -----------------------------------------------------------------

    public function synchroniserVersMikroTik(Voucher $voucher): bool
    {
        try {
            $existing = $this->mikroTik->findHotspotUser($voucher->username);

            if ($existing === null) {
                $limiteUptime = (int) $voucher->duree > 0
                    ? MikroTikService::formatUptime((int) $voucher->duree)
                    : null;

                $limiteData = (int) $voucher->limite_data_mo > 0
                    ? (string) ((int) $voucher->limite_data_mo * 1024 * 1024)
                    : null;

                $this->mikroTik->createHotspotUser(
                    username:        $voucher->username,
                    password:        $voucher->password,
                    profile:         $voucher->profil ?: config('mikrotik.hotspot_profile', 'default'),
                    comment:         $voucher->description ?: (($voucher->estReutilisable() ? 'CM réutilisable #' : 'CM voucher #') . $voucher->id),
                    limitUptime:     $limiteUptime,
                    server:          $voucher->serveur,
                    limitBytesTotal: $limiteData,
                );

                $existing = $this->mikroTik->findHotspotUser($voucher->username);
            }

            $voucher->update([
                'mikrotik_id'         => $existing['.id'] ?? null,
                'mikrotik_synced_at'  => now(),
                'mikrotik_sync_error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Sync MikroTik échouée pour l’identifiant ' . $voucher->id, [
                'username' => $voucher->username,
                'error'    => $e->getMessage(),
            ]);

            $voucher->update(['mikrotik_sync_error' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array{ok:int, ko:int, restants:int} */
    public function synchroniserVouchersEnAttente(?int $limite = null): array
    {
        $query = Voucher::whereNull('mikrotik_synced_at')->orderBy('id');

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $ok = 0;
        $ko = 0;

        foreach ($query->get() as $voucher) {
            $this->synchroniserVersMikroTik($voucher) ? $ok++ : $ko++;
        }

        $restants = Voucher::whereNull('mikrotik_synced_at')->count();

        return ['ok' => $ok, 'ko' => $ko, 'restants' => $restants];
    }

    // -----------------------------------------------------------------
    // Protection
    // -----------------------------------------------------------------

    public function basculerProtection(Voucher $voucher): Voucher
    {
        $voucher->update(['protege' => !$voucher->protege]);

        return $voucher->fresh();
    }

    // -----------------------------------------------------------------
    // Suppression
    // -----------------------------------------------------------------

    public function supprimer(Voucher $voucher): void
    {
        if ($voucher->etat === 'en_cours') {
            $this->deconnecterSiPossible($voucher);
        }

        try {
            $this->mikroTik->deleteHotspotUser($voucher->username);
        } catch (\Throwable $e) {
            Log::warning('Suppression MikroTik échouée pour l’identifiant ' . $voucher->id, [
                'username' => $voucher->username,
                'error'    => $e->getMessage(),
            ]);
        }

        $voucher->delete();
    }

    /**
     * @param  int[]  $ids
     * @param  bool   $inclureProteges  true après saisie du username (déprotection explicite)
     * @return array{supprimes:int, proteges_ignores:int, echecs:int}
     */
    public function supprimerEnMasse(array $ids, bool $inclureProteges = false): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $resultat = ['supprimes' => 0, 'proteges_ignores' => 0, 'echecs' => 0];

        foreach (Voucher::whereIn('id', $ids)->get() as $voucher) {
            if ($voucher->protege && !$inclureProteges) {
                $resultat['proteges_ignores']++;
                continue;
            }

            try {
                $this->supprimer($voucher);
                $resultat['supprimes']++;
            } catch (\Throwable $e) {
                Log::warning('Suppression en masse échouée', [
                    'voucher_id' => $voucher->id,
                    'error'      => $e->getMessage(),
                ]);
                $resultat['echecs']++;
            }
        }

        return $resultat;
    }

    private function deconnecterSiPossible(Voucher $voucher): void
    {
        $session = $voucher->sessions()->where('etat', 'en_cours')->latest('id')->first();
        $mac = $session->client_mac ?? $voucher->appareilWifi?->adresse_mac;

        if ($mac === null) {
            return;
        }

        try {
            $this->mikroTik->disconnectHotspotActiveByMac($mac);
        } catch (\Throwable $e) {
            Log::warning('Déconnexion MikroTik échouée avant suppression de l’identifiant ' . $voucher->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Statistiques
    // -----------------------------------------------------------------

    /** @return array<string,int|string> */
    public function statistiques(): array
    {
        $parEtat = Voucher::query()->selectRaw('etat, COUNT(*) as n')->groupBy('etat')->pluck('n', 'etat');

        return [
            'total'            => (int) $parEtat->sum(),
            'disponible'       => (int) ($parEtat['disponible'] ?? 0),
            'en_cours'         => (int) ($parEtat['en_cours'] ?? 0),
            'utilise'          => (int) ($parEtat['utilise'] ?? 0),
            'proteges'         => Voucher::where('protege', true)->count(),
            'non_sync'         => Voucher::whereNull('mikrotik_synced_at')->count(),
            'pool_disponibles' => Voucher::porteeTemporaires()->porteeDisponibles()->count(),
            'pool_seuil'       => (int) config('mikrotik.voucher_pool_seuil', 10),
            'pool_taille'      => (int) config('mikrotik.voucher_pool_taille', 40),
            'pool_mode'        => Parametre::get('hotspot_pool_mode', 'auto'),
        ];
    }
}