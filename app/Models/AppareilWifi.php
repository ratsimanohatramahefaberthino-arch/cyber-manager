<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class AppareilWifi extends Model
{
    protected $table = 'appareils_wifi';

    public const TYPES = ['telephone', 'ordinateur', 'tablette', 'inconnu'];

    protected $fillable = [
        'adresse_mac',
        'adresse_ip',
        'host_name',
        'nom_affichage',   // ← AJOUTÉ : nom personnalisé choisi par le personnel
        'type_appareil',
        'fabricant',
        'modele',
        'systeme',
        'user_agent',
        'premiere_connexion',
        'derniere_connexion',
    ];

    protected $casts = [
        'premiere_connexion' => 'datetime',
        'derniere_connexion' => 'datetime',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'appareil_wifi_id');
    }

    /**
     * Normalise une MAC au format AA:BB:CC:DD:EE:FF.
     * Retourne null si la valeur n'est pas une MAC valide (12 hexa).
     */
    public static function normaliserMac(?string $mac): ?string
    {
        if ($mac === null) {
            return null;
        }

        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    /**
     * Retrouve l'appareil par sa MAC, ou le crée.
     *
     * - Les attributs vides/null ne PEUVENT PAS écraser une valeur existante.
     * - Un type "inconnu" n'écrase jamais un type déjà connu.
     * - Ne touche pas derniere_connexion sur un appareil existant
     *   (voir toucherDerniereConnexion()).
     *
     * @param  array<string,mixed>  $attrs  adresse_ip, host_name, type_appareil,
     *                                      fabricant, modele, systeme, user_agent
     */
    public static function trouverOuCreerParMac(string $mac, array $attrs = []): self
    {
        $macNormalisee = self::normaliserMac($mac);

        if ($macNormalisee === null) {
            throw new InvalidArgumentException("Adresse MAC invalide : {$mac}");
        }

        $attrs = Arr::only($attrs, [
            'adresse_ip', 'host_name', 'type_appareil',
            'fabricant', 'modele', 'systeme', 'user_agent',
            'nom_affichage',   // ← AJOUTÉ : on autorise aussi ce champ à être passé
        ]);

        $attrs = array_filter($attrs, fn ($v) => $v !== null && $v !== '');

        if (
            isset($attrs['type_appareil'])
            && (
                $attrs['type_appareil'] === 'inconnu'
                || !in_array($attrs['type_appareil'], self::TYPES, true)
            )
        ) {
            unset($attrs['type_appareil']);
        }

        $appareil = self::where('adresse_mac', $macNormalisee)->first();

        if ($appareil === null) {
            try {
                return self::create(array_merge(
                    ['type_appareil' => 'inconnu'],
                    $attrs,
                    [
                        'adresse_mac'        => $macNormalisee,
                        'premiere_connexion' => now(),
                        'derniere_connexion' => now(),
                    ]
                ));
            } catch (QueryException $e) {
                // Course critique : un autre process vient de le créer
                $appareil = self::where('adresse_mac', $macNormalisee)->first();

                if ($appareil === null) {
                    throw $e;
                }
            }
        }

        $appareil->fill($attrs);

        if ($appareil->isDirty()) {
            $appareil->save();
        }

        return $appareil;
    }

    /**
     * Met à jour la date de dernière connexion (et la première si absente).
     */
    public function toucherDerniereConnexion(): void
    {
        $maintenant = now();

        $this->derniere_connexion = $maintenant;

        if ($this->premiere_connexion === null) {
            $this->premiere_connexion = $maintenant;
        }

        $this->save();
    }

    /**
     * Nom affiché dans l'interface, par ordre de priorité :
     *  1. nom_affichage : choisi manuellement par le personnel (fiable à 100%) ;
     *  2. host_name : gardé seulement s'il semble parlant (on écarte les
     *     identifiants génériques type "android-1339b7ea..." qui ne disent rien) ;
     *  3. repli générique + 4 derniers caractères de la MAC, en attendant
     *     qu'on le renomme depuis le tableau.
     */
    public function libelle(): string
    {
        if (!empty($this->nom_affichage)) {
            return $this->nom_affichage;
        }

        $h = $this->host_name;
        $generique = $h !== null && preg_match('/^(android|localhost|iphone|ipad|unknown)[-_]?[0-9a-f]{4,}$/i', $h);

        if ($h !== null && $h !== '' && !$generique && mb_strlen($h) >= 3) {
            return str_replace(['-', '_'], ' ', $h);
        }

        $libelleType = match ($this->type_appareil) {
            'telephone'  => 'Téléphone',
            'tablette'   => 'Tablette',
            'ordinateur' => 'Ordinateur',
            default      => 'Appareil',
        };

        $suffixe = $this->adresse_mac ? substr(str_replace(':', '', $this->adresse_mac), -4) : '????';

        return "{$libelleType} · {$suffixe}";
    }
}