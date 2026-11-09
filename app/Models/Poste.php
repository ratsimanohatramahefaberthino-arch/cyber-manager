<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poste extends Model
{
    public const ETAT_DISPONIBLE  = 'disponible';
    public const ETAT_OCCUPE      = 'occupe';
    public const ETAT_SUSPENDU    = 'suspendu';
    public const ETAT_VERROUILLE  = 'verrouille';
    public const ETAT_DESACTIVE   = 'desactive';
    public const ETAT_HORS_LIGNE  = 'hors_ligne';

    /** Délai (secondes) sans heartbeat avant de considérer le poste hors ligne. */
    public const DELAI_HORS_LIGNE = 30;

    protected $fillable = [
        'nom_poste', 'nom_windows', 'adresse_mac', 'adresse_ip',
        'type_connexion', 'etat', 'actif', 'derniere_communication',
        'agent_url', 'identifiant_unique', 'agent_token',
        'dernier_heartbeat', 'agent_version',
        'hotspot_username', 'hotspot_password',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'derniere_communication' => 'datetime',
        'dernier_heartbeat' => 'datetime',
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
}