<?php

namespace App\Services;

use App\Models\PortailParametre;
use Illuminate\Support\Facades\DB;

/**
 * Réglages du portail captif : valeurs par défaut, lecture « nettoyée », écriture groupée.
 * Source unique pour l'administration, la page de connexion et le tableau de bord client.
 */
final class PortailConfig
{
    /** Nom proposé tant que le gérant n'en a pas choisi un. */
    public const DOMAINE_DEFAUT = 'portail.izara';

    /**
     * Polices proposées : pile CSS => libellé.
     * Uniquement des polices déjà présentes sur les appareils : avant connexion,
     * le téléphone n'a pas Internet et ne peut rien télécharger.
     */
    public const POLICES = [
        'Inter, system-ui, sans-serif'                        => 'Moderne (recommandée)',
        'Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif' => 'Classique',
        'Verdana, Tahoma, sans-serif'                         => 'Large et très lisible',
        'Georgia, Times New Roman, serif'                     => 'Élégante',
    ];

    /** Tous les réglages enregistrés dans portail_parametres, sauf le logo (géré à part). */
    public const CHAMPS = [
        'titre', 'titre_taille', 'titre_couleur', 'message', 'message_couleur', 'police',
        'fond_type', 'fond_couleur_1', 'fond_couleur_2', 'carte_couleur', 'carte_texte',
        'bouton_fond', 'bouton_texte', 'bouton_rayon', 'accent_couleur',
        'annonce_active', 'annonce_texte', 'annonce_fond', 'annonce_texte_color', 'footer_texte',
    ];

    private const COULEURS = [
        'titre_couleur', 'message_couleur', 'fond_couleur_1', 'fond_couleur_2', 'carte_couleur',
        'carte_texte', 'bouton_fond', 'bouton_texte', 'accent_couleur', 'annonce_fond', 'annonce_texte_color',
    ];

    public static function defauts(): array
    {
        return [
            'titre'               => 'Bienvenue au Cybercafé',
            'titre_taille'        => '26',
            'titre_couleur'       => '#ffffff',
            'message'             => 'Veuillez vous connecter avec vos identifiants pour accéder à Internet.',
            'message_couleur'     => '#b9d3d4',
            'police'              => array_key_first(self::POLICES),
            'fond_type'           => 'gradient',
            'fond_couleur_1'      => '#0b1f24',
            'fond_couleur_2'      => '#1b5c63',
            'carte_couleur'       => '#f4f7f6',
            'carte_texte'         => '#10262b',
            'bouton_fond'         => '#ffb020',
            'bouton_texte'        => '#2b1c00',
            'bouton_rayon'        => '16',
            'accent_couleur'      => '#3dd6d0',
            'annonce_active'      => '0',
            'annonce_texte'       => 'Profitez d\'une connexion haut débit stable et sécurisée !',
            'annonce_fond'        => '#ffb020',
            'annonce_texte_color' => '#2b1c00',
            'footer_texte'        => 'Demandez votre ticket d\'accès ou votre recharge à la caisse.',
            'logo'                => null,
        ];
    }

    /** Réglages prêts à afficher : une seule requête, valeurs invalides remplacées par les valeurs par défaut. */
    public static function charger(): array
    {
        $defauts = self::defauts();

        try {
            $lignes = PortailParametre::query()->pluck('valeur', 'cle')->all();
        } catch (\Throwable) {
            $lignes = [];
        }

        $c = [];
        foreach ($defauts as $cle => $defaut) {
            $c[$cle] = array_key_exists($cle, $lignes) ? $lignes[$cle] : $defaut;
        }

        foreach (self::COULEURS as $k) {
            if (!is_string($c[$k]) || !preg_match('/^#[0-9a-fA-F]{6}$/', $c[$k])) {
                $c[$k] = $defauts[$k];
            }
        }
        foreach (['titre', 'message'] as $k) {
            if (!filled($c[$k])) {
                $c[$k] = $defauts[$k];
            }
        }
        foreach (['annonce_texte', 'footer_texte'] as $k) {
            $c[$k] = (string) ($c[$k] ?? '');
        }

        $c['titre_taille'] = max(18, min(36, (int) $c['titre_taille']));
        $c['bouton_rayon'] = max(0, min(99, (int) $c['bouton_rayon']));
        if (!is_string($c['police']) || !isset(self::POLICES[$c['police']])) {
            $c['police'] = $defauts['police'];
        }
        if (!in_array($c['fond_type'], ['gradient', 'solid'], true)) {
            $c['fond_type'] = 'gradient';
        }
        $c['annonce_active'] = (string) $c['annonce_active'] === '1' ? '1' : '0';
        $c['logo'] = filled($c['logo']) ? $c['logo'] : null;

        return $c;
    }

    /** Écrit plusieurs réglages en une seule requête. */
    public static function enregistrer(array $valeurs): void
    {
        $now  = now();
        $rows = [];
        foreach ($valeurs as $cle => $valeur) {
            $rows[] = ['cle' => $cle, 'valeur' => $valeur, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('portail_parametres')->upsert($rows, ['cle'], ['valeur', 'updated_at']);
    }
}