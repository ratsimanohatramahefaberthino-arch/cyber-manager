<?php

namespace App\Services;

use App\Models\Poste;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Contrôle d'accès Internet d'un poste, SANS identifiant.
 * Principe : le serveur autorise / retire l'adresse MAC du poste sur le routeur.
 * Pilote disponible : MikroTik (HotSpot, ip-binding). Sans routeur configuré : « non contrôlé » (rien ne casse).
 */
final class AccesInternetService
{
    public const AUTORISE = 'autorise';
    public const COUPE    = 'coupe';
    public const ERREUR   = 'erreur';
    public const NON_GERE = 'non_gere';

    private const MARQUE = 'Cyber Manager - Poste';

    public function disponible(): bool
    {
        return (new MikroTikService())->estConfigure();
    }

    /** @return bool|null true = fait, false = échec routeur, null = aucun routeur (non contrôlé) */
    public function autoriser(Poste $poste): ?bool
    {
        return $this->appliquer($poste, true);
    }

    public function couper(Poste $poste): ?bool
    {
        return $this->appliquer($poste, false);
    }

    /** Règle unique : Internet seulement si la session tourne ET que le poste donne signe de vie. */
    public function souhaite(Poste $poste): bool
    {
        $s = $poste->sessionActive();

        return $s !== null && $s->etat === 'en_cours' && $poste->estEnLigne();
    }

    /**
     * Remet les routeurs d'aplomb (au plus une fois / 10 s). Appelé par les heartbeats et la page Postes.
     * Un poste qui disparaît (câble, panne, agent tué) perd Internet environ 30 s plus tard.
     */
    public function reconcilierTous(): void
    {
        if (Cache::has('acces.pause') || !Cache::add('acces.reconcilier', 1, 10)) {
            return;
        }

        try {
            if (!$this->disponible()) {
                return;
            }

            $postes = Poste::whereNotNull('adresse_mac')->where('adresse_mac', '!=', '')->get();
            foreach ($postes as $p) {
                $voulu = $this->souhaite($p);
                if ($p->internet_etat === ($voulu ? self::AUTORISE : self::COUPE)) {
                    continue;
                }
                if ($this->appliquer($p, $voulu) === false) {
                    Cache::put('acces.pause', 1, 30);   // routeur en panne : on ne bloque pas les requêtes
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Accès Internet : réconciliation échouée (' . $e->getMessage() . ').');
        }
    }

    private function appliquer(Poste $poste, bool $autoriser): ?bool
    {
        $mt = new MikroTikService(['timeout' => 3.0]);

        if (!$mt->estConfigure()) {
            $this->noter($poste, self::NON_GERE);
            return null;
        }

        $mac = trim((string) $poste->adresse_mac);
        if ($mac === '') {
            $this->noter($poste, self::ERREUR);
            return false;
        }

        try {
            if ($autoriser) {
                $mt->autoriserMac($mac, self::MARQUE . ' : ' . $poste->nom_poste);
            } else {
                $mt->revoquerMac($mac);
            }
        } catch (\Throwable $e) {
            Log::warning("Internet du poste « {$poste->nom_poste} » : commande routeur échouée ({$e->getMessage()}).");
            $this->noter($poste, self::ERREUR);
            return false;
        }

        $this->noter($poste, $autoriser ? self::AUTORISE : self::COUPE);
        return true;
    }

    private function noter(Poste $poste, string $etat): void
    {
        Poste::whereKey($poste->id)->update(['internet_etat' => $etat, 'internet_maj_at' => now()]);
        $poste->internet_etat = $etat;
    }
}