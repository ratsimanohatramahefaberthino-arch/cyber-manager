<?php

namespace App\Services;

use App\Models\CyberSetting;
use App\Models\Poste;

/** Profil de restrictions Windows (compte Client). Stocké dans cyber_settings, clé « restrictions ». */
class RestrictionsService
{
    /** clé => [libellé, aide] */
    public const CLES = [
        'gestionnaire_taches' => ['Gestionnaire des tâches', 'Retiré du menu Ctrl+Alt+Suppr et inutilisable.'],
        'verrouiller'         => ['Verrouiller', 'Retiré du menu Ctrl+Alt+Suppr, Windows+L inactif.'],
        'deconnexion'         => ['Se déconnecter', 'Retiré du menu Ctrl+Alt+Suppr et du menu Démarrer.'],
        'cmd'                 => ['Invite de commandes', 'Bloque cmd.exe.'],
        'powershell'          => ['PowerShell', 'Bloque PowerShell lancé depuis Windows (Explorateur, menu Démarrer).'],
        'regedit'             => ['Éditeur de registre', 'Bloque regedit.'],
        'executer'            => ['« Exécuter » (Windows+R)', 'Supprime la boîte Exécuter.'],
        'panneau'             => ['Panneau de configuration et Paramètres', 'Bloque les deux.'],
    ];

    private const CLE_BDD = 'restrictions';

    /** @return array<string,bool> Par défaut TOUT est bloqué tant que le gérant n'a rien décoché. */
    public static function profil(): array
    {
        $profil = array_fill_keys(array_keys(self::CLES), true);

        try {
            $brut = CyberSetting::query()->where('cle', self::CLE_BDD)->value('valeur');
        } catch (\Throwable) {
            $brut = null;
        }

        if ($brut !== null && $brut !== '') {
            $actives = array_filter(explode(',', (string) $brut));
            foreach ($profil as $cle => $_) {
                $profil[$cle] = in_array($cle, $actives, true);
            }
        }

        return $profil;
    }

    /** @param array<string,bool> $coches */
    public static function enregistrer(array $coches): void
    {
        $actives = [];
        foreach (array_keys(self::CLES) as $cle) {
            if (!empty($coches[$cle])) {
                $actives[] = $cle;
            }
        }
        // Valeur « _ » = aucune restriction (distincte de « jamais configuré »)
        CyberSetting::definir(self::CLE_BDD, $actives ? implode(',', $actives) : '_');
    }

    /** Envoyé au Gardien : profil + maintenance de CE poste. */
    public static function pourPoste(Poste $poste): array
    {
        return ['maintenance' => (bool) $poste->mode_maintenance] + self::profil();
    }
}