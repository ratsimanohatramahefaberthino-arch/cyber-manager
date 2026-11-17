<?php

namespace App\Services;

use App\Models\Poste;

/**
 * Empreinte matérielle d'un poste : UUID SMBIOS + MAC des cartes physiques.
 * Elle ne se clone pas : un disque copié sur un autre PC porte une autre empreinte.
 */
final class IdentiteMaterielle
{
    /** @return array{uuid:string, macs:string[]}|null */
    public static function normaliser(mixed $brut): ?array
    {
        if (!is_array($brut)) {
            return null;
        }

        $uuid = strtoupper(trim((string) ($brut['uuid'] ?? '')));
        if (!preg_match('/^[0-9A-F]{8}(-[0-9A-F]{4}){3}-[0-9A-F]{12}$/', $uuid)) {
            $uuid = '';
        }

        $macs = [];
        foreach ((array) ($brut['macs'] ?? []) as $m) {
            $m = strtoupper(str_replace('-', ':', trim((string) $m)));
            if (preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $m)) {
                $macs[$m] = true;
            }
        }
        $macs = array_slice(array_keys($macs), 0, 8);
        sort($macs);

        return ($uuid === '' && $macs === []) ? null : ['uuid' => $uuid, 'macs' => $macs];
    }

    /** Empreinte mémorisée pour ce poste (null = ancien poste, jamais vue). */
    public static function dePoste(Poste $p): ?array
    {
        $macs = json_decode((string) $p->empreinte_macs, true);

        return self::normaliser(['uuid' => $p->empreinte_uuid, 'macs' => is_array($macs) ? $macs : []]);
    }

    /** true = même ordinateur · false = autre ordinateur · null = comparaison impossible. */
    public static function memeMachine(?array $a, ?array $b): ?bool
    {
        if ($a === null || $b === null) {
            return null;
        }

        if ($a['macs'] !== [] && $b['macs'] !== []) {
            return array_intersect($a['macs'], $b['macs']) !== [];
        }

        // L'un des deux n'a pas de MAC lisible : on se rabat sur l'UUID
        if ($a['uuid'] !== '' && $b['uuid'] !== '') {
            return $a['uuid'] === $b['uuid'];
        }

        return null;
    }

    /** Adopte l'empreinte (et le nom Windows) reçue. N'écrit que si quelque chose a changé. */
    public static function enregistrer(Poste $p, ?array $emp, ?string $nomWindows = null): void
    {
        $maj = [];

        if ($emp !== null && $emp !== self::dePoste($p)) {
            $maj['empreinte_uuid'] = $emp['uuid'] !== '' ? $emp['uuid'] : null;
            $maj['empreinte_macs'] = json_encode($emp['macs']);
        }
        if ($nomWindows !== null && $nomWindows !== '' && $nomWindows !== $p->nom_windows) {
            $maj['nom_windows'] = $nomWindows;
        }

        if ($maj !== []) {
            Poste::whereKey($p->id)->update($maj);
        }
    }

    /**
     * Identifiant inconnu : s'agit-il d'un poste déjà enregistré dont le système a été réinstallé ?
     * - empreintes connues des deux côtés : même machine ;
     * - ancien poste sans empreinte : même MAC ET même nom (Windows ou poste).
     */
    public static function retrouverPourReinstallation(?array $emp, ?string $mac, string $nomWindows, string $nomPoste): ?Poste
    {
        foreach (Poste::whereNotNull('identifiant_unique')->get() as $p) {
            $stock = self::dePoste($p);

            if ($emp !== null && $stock !== null) {
                if (self::memeMachine($stock, $emp) === true) {
                    return $p;
                }
                continue;
            }

            if ($mac !== null && $p->adresse_mac !== null && strtoupper($p->adresse_mac) === $mac) {
                $memeNom = ($nomWindows !== '' && strcasecmp((string) $p->nom_windows, $nomWindows) === 0)
                    || strcasecmp($p->nom_poste, $nomPoste) === 0;
                if ($memeNom) {
                    return $p;
                }
            }
        }

        return null;
    }
}