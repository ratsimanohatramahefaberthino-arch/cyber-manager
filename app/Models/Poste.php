<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Poste extends Model
{
    public const ETAT_DISPONIBLE  = 'disponible';
    public const ETAT_OCCUPE      = 'occupe';
    public const ETAT_SUSPENDU    = 'suspendu';
    public const ETAT_VERROUILLE  = 'verrouille';
    public const ETAT_DESACTIVE   = 'desactive';
    public const ETAT_HORS_LIGNE  = 'hors_ligne';

    /** Délai (secondes) sans heartbeat avant de considérer le poste hors ligne. */
    public const DELAI_HORS_LIGNE = 180;

    /**
     * Deux signes de vie venant de deux adresses réseau différentes à moins de ce délai (secondes)
     * = deux ordinateurs utilisent la même identité (PC cloné).
     */
    public const DELAI_CONFLIT = 12;

    /** Un conflit signalé s'efface tout seul après ce nombre de minutes sans nouveau signalement. */
    public const DUREE_CONFLIT_MINUTES = 10;

    /** Délai (secondes) sans réponse du serveur avant que l'AGENT verrouille le poste (3 minutes). */
    public const DELAI_GRACE_AGENT = 180;

    protected $fillable = [
        'nom_poste', 'nom_windows', 'adresse_mac', 'adresse_ip',
        'type_connexion', 'etat', 'actif', 'derniere_communication',
        'agent_url', 'identifiant_unique', 'agent_token',
        'dernier_heartbeat', 'agent_version',
        'hotspot_username', 'hotspot_password',
        'derniere_ip_vue', 'conflit_identite_at',
        'internet_etat', 'internet_maj_at',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'derniere_communication' => 'datetime',
        'dernier_heartbeat' => 'datetime',
        'conflit_identite_at' => 'datetime',
        'internet_maj_at' => 'datetime',
    ];

    protected $hidden = ['agent_token', 'hotspot_password'];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(PosteCommande::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PosteMessage::class);
    }

    public function sessionActive(): ?Session
    {
        return $this->sessions()
            ->whereIn('etat', ['en_cours', 'en_pause'])
            ->latest()
            ->first();
    }

    public function estEnLigne(): bool
    {
        return $this->dernier_heartbeat
            && $this->dernier_heartbeat->gt(now()->subSeconds(self::DELAI_HORS_LIGNE));
    }

    public function estConnecte(): bool
    {
        return $this->estEnLigne()
            && $this->etat !== self::ETAT_HORS_LIGNE
            && $this->etat !== self::ETAT_DESACTIVE;
    }

    public function etatAffiche(): string
    {
        if ($this->etat === self::ETAT_DESACTIVE) {
            return self::ETAT_DESACTIVE;
        }
        if (!$this->estEnLigne()) {
            return self::ETAT_HORS_LIGNE;
        }
        if ($this->etat === self::ETAT_HORS_LIGNE) {
            return $this->sessionActive() ? self::ETAT_OCCUPE : self::ETAT_DISPONIBLE;
        }
        return $this->etat;
    }

    /** Un conflit d'identité (PC cloné) est-il signalé pour ce poste ? */
    public function aConflitIdentite(): bool
    {
        return $this->conflit_identite_at !== null;
    }

    // ------------------------------------------------------------------
    // Noms de postes
    // ------------------------------------------------------------------

    /** Nettoie un nom : pas de caractères de contrôle, espaces simplifiés, 100 caractères maximum. */
    public static function nettoyerNom(string $nom): string
    {
        $nom = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $nom) ?? '';
        $nom = preg_replace('/\s+/u', ' ', $nom) ?? '';

        return mb_substr(trim($nom), 0, 100);
    }

    /** Ce nom (majuscules/minuscules ignorées) est-il déjà pris par un autre poste ? */
    public static function nomExiste(string $nom, ?int $ignorerId = null): bool
    {
        return static::query()
            ->whereRaw('LOWER(nom_poste) = ?', [mb_strtolower($nom)])
            ->when($ignorerId !== null, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->exists();
    }

    /** Si le nom est pris, ajoute un suffixe : « Poste 3 » devient « Poste 3-2 », « Poste 3-3 »… */
    public static function nomDisponible(string $nom, ?int $ignorerId = null): string
    {
        $candidat = $nom;
        $i = 2;

        while (static::nomExiste($candidat, $ignorerId)) {
            $suffixe  = '-' . $i;
            $candidat = mb_substr($nom, 0, 100 - mb_strlen($suffixe)) . $suffixe;
            $i++;

            if ($i > 500) {   // garde-fou : ne jamais boucler indéfiniment
                $candidat = mb_substr($nom, 0, 90) . '-' . Str::lower(Str::random(6));
                break;
            }
        }

        return $candidat;
    }

    /**
     * Nom attribué à un NOUVEAU poste :
     *  - nom vide ou nom Windows par défaut (DESKTOP-XXXXXXX…) → « Poste 1 », « Poste 2 »…
     *  - sinon le nom demandé, avec un suffixe s'il est déjà pris.
     */
    public static function nomPourNouveauPoste(string $demande): string
    {
        $nom = self::nettoyerNom($demande);

        if ($nom === '' || preg_match('/^(DESKTOP|LAPTOP|WIN)-[A-Z0-9]{5,}$/i', $nom)) {
            $n = 1;
            while (static::nomExiste("Poste {$n}")) {
                $n++;
            }

            return "Poste {$n}";
        }

        return self::nomDisponible($nom);
    }
}