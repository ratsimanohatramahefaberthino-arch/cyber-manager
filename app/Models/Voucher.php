<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    /** Réutilisable (revient "disponible" après chaque session) ou usage unique. */
    public const TYPE_TEMPORAIRE = 'temporaire';
    public const TYPE_PERMANENT  = 'permanent';

    /** États HotSpot : rien d'autre que ça. */
    public const ETATS = ['disponible', 'en_cours', 'utilise'];

    protected $fillable = [
        'lot_voucher_id',
        'username',
        'nom',
        'password',
        'code',
        'montant',
        'duree',
        'etat',
        'source',
        'type_voucher',
        'protege',
        'serveur',          // ← AJOUTÉ
        'profil',           // ← AJOUTÉ
        'limite_data_mo',   // ← AJOUTÉ
        'appareil_wifi_id',
        'mikrotik_id',
        'date_heure_creation',
        'date_heure_utilisation',
        'date_heure_expiration',
        'derniere_synchronisation',
        'description',
        'mikrotik_synced_at',
        'mikrotik_sync_error',
    ];

    protected $casts = [
        'montant' => 'integer',
        'duree' => 'integer',
        'protege' => 'boolean',
        'limite_data_mo' => 'integer',   // ← AJOUTÉ
        'date_heure_creation' => 'datetime',
        'date_heure_utilisation' => 'datetime',
        'date_heure_expiration' => 'datetime',
        'derniere_synchronisation' => 'datetime',
        'mikrotik_synced_at' => 'datetime',
        'mikrotik_sync_error' => 'string',
    ];

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotVoucher::class, 'lot_voucher_id');
    }

    public function session(): HasOne
    {
        return $this->hasOne(Session::class, 'voucher_id');
    }

    /** Toutes les sessions Wi-Fi ouvertes avec cet identifiant (réutilisable : plusieurs). */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'voucher_id');
    }

    public function appareilWifi(): BelongsTo
    {
        return $this->belongsTo(AppareilWifi::class, 'appareil_wifi_id');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    public function scopePorteeTemporaires(Builder $query): Builder
    {
        return $query->where('type_voucher', self::TYPE_TEMPORAIRE);
    }

    public function scopePorteePermanents(Builder $query): Builder
    {
        return $query->where('type_voucher', self::TYPE_PERMANENT);
    }

    public function scopePorteeDisponibles(Builder $query): Builder
    {
        return $query->where('etat', 'disponible');
    }

    /**
     * Filtres de la liste HotSpot.
     * @param array<string,mixed> $f  etat, q, reutilisable (oui/non), protege (oui/non), sync, lot
     */
    public function scopeFiltrer(Builder $query, array $f): Builder
    {
        $etat = $f['etat'] ?? null;
        if (in_array($etat, self::ETATS, true)) {
            $query->where('etat', $etat);
        }

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $terme = addcslashes(mb_substr($q, 0, 64), '%_\\');
            $query->where(function (Builder $w) use ($terme) {
                $w->where('username', 'like', "%{$terme}%")
                  ->orWhere('nom', 'like', "%{$terme}%");
            });
        }

        $reutilisable = $f['reutilisable'] ?? null;
        if ($reutilisable === 'oui') {
            $query->where('type_voucher', self::TYPE_PERMANENT);
        } elseif ($reutilisable === 'non') {
            $query->where('type_voucher', self::TYPE_TEMPORAIRE);
        }

        $protege = $f['protege'] ?? null;
        if ($protege === 'oui') {
            $query->where('protege', true);
        } elseif ($protege === 'non') {
            $query->where('protege', false);
        }

        $sync = $f['sync'] ?? null;
        if ($sync === 'synced') {
            $query->whereNotNull('mikrotik_synced_at');
        } elseif ($sync === 'not_synced') {
            $query->whereNull('mikrotik_synced_at');
        }

        $lot = $f['lot'] ?? null;
        if ($lot !== null && $lot !== '') {
            $query->where('lot_voucher_id', (int) $lot);
        }

        return $query;
    }

    // -----------------------------------------------------------------
    // Métier
    // -----------------------------------------------------------------

    /** Revient "disponible" après chaque session, au lieu de passer "utilise". */
    public function estReutilisable(): bool
    {
        return $this->type_voucher === self::TYPE_PERMANENT;
    }

    public function iconeAppareil(): string
    {
        return match ($this->appareilWifi?->type_appareil) {
            'telephone' => 'phone',
            'tablette'  => 'tablet',
            default     => 'desktop',
        };
    }

    /** Volume total consommé (toutes sessions), lisible. Nécessite withSum('sessions as volume_total_octets','volume_total'). */
    public function volumeLisible(): string
    {
        $octets = (int) ($this->volume_total_octets ?? 0);

        if ($octets <= 0) {
            return '—';
        }

        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $valeur = $octets;

        while ($valeur >= 1024 && $i < count($unites) - 1) {
            $valeur /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $valeur : round($valeur, 1)) . ' ' . $unites[$i];
    }

    // -----------------------------------------------------------------
    // Machine à états — 3 valeurs seulement
    // -----------------------------------------------------------------

    /**
     * Ouverture / poursuite d'une session. Rattache l'appareil :
     *  - usage unique : dernier appareil ayant utilisé l'identifiant ;
     *  - réutilisable  : premier appareil (jamais écrasé).
     */
    public function marquerEnCours(?AppareilWifi $appareil = null): bool
    {
        if (!in_array($this->etat, ['disponible', 'en_cours', 'utilise'], true)) {
            return false;
        }

        $maj = ['etat' => 'en_cours'];

        if ($this->date_heure_utilisation === null) {
            $maj['date_heure_utilisation'] = now();
        }

        if ($appareil !== null) {
            $rattacher = $this->estReutilisable()
                ? $this->appareil_wifi_id === null
                : $this->appareil_wifi_id !== $appareil->id;

            if ($rattacher) {
                $maj['appareil_wifi_id'] = $appareil->id;
            }
        }

        $this->update($maj);

        return true;
    }

    /** Fin de session d'un identifiant à usage unique. */
    public function marquerUtilise(): bool
    {
        return $this->changerEtat(['en_cours'], 'utilise');
    }

    /** Fin de session d'un identifiant réutilisable : retour à disponible. */
    public function marquerDisponible(): bool
    {
        return $this->changerEtat(['en_cours'], 'disponible');
    }

    private function changerEtat(array $depuis, string $vers): bool
    {
        if (!in_array($this->etat, $depuis, true)) {
            return false;
        }

        $this->update(['etat' => $vers]);

        return true;
    }

    // -----------------------------------------------------------------
    // Présentation
    // -----------------------------------------------------------------

    public function couleurEtat(): string
    {
        return match ($this->etat) {
            'disponible' => 'emerald',
            'en_cours'   => 'amber',
            'utilise'    => 'slate',
            default      => 'slate',
        };
    }

    public function libelleEtat(): string
    {
        return match ($this->etat) {
            'disponible' => 'Disponible',
            'en_cours'   => 'En cours',
            'utilise'    => 'Utilisé',
            default      => (string) $this->etat,
        };
    }

    /** Couleur de fond de la ligne dans le tableau, selon l'état. */
    public function classeLigne(): string
    {
        return match ($this->etat) {
            'en_cours' => 'bg-amber-50',
            'utilise'  => 'bg-slate-100',
            default    => 'bg-white', // disponible
        };
    }
}