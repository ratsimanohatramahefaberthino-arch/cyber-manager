<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    protected $table = 'cyber_sessions';

    public const WIFI_CONNECTE   = 'connecte';
    public const WIFI_DECONNECTE = 'deconnecte';
    public const WIFI_TERMINE    = 'termine';
    public const WIFI_EN_PAUSE   = 'en_pause';

    protected $fillable = [
        'poste_id', 'type_session',
        'date_heure_debut', 'date_heure_fin_prevue', 'date_heure_fin_reelle',
        'date_heure_suspension', 'date_heure_reprise', 'duree_suspension',
        'duree_prevue', 'duree_consommee', 'temps_restant',
        'montant_initial', 'montant_recharge', 'montant_total',
        'montant_consomme', 'montant_restant', 'montant_a_reverser',
        'description', 'etat', 'motif_fin', 'tarif_id', 'voucher_id',
        'volume_entree', 'volume_sortie', 'volume_total',
        'mikrotik_active_id', 'hotspot_username', 'client_mac', 'client_ip',
        'ssid', 'appareil_wifi_id', 'derniere_sync_at',
        'etat_wifi', 'deconnecte_at', 'reconnecte_at', 'duree_hors_ligne_secondes',
        'pause_at', 'duree_pause_secondes',
        'alarme_active', 'alarme_type', 'alarme_duree_minutes', 'alarme_montant',
        'alarme_fin_prevue', 'alarme_declenchee_at', 'alarme_message',
        'alarme_historique', 'alarme_secondes_restantes',
    ];

    protected $casts = [
        'date_heure_debut' => 'datetime',
        'date_heure_fin_prevue' => 'datetime',
        'date_heure_fin_reelle' => 'datetime',
        'date_heure_suspension' => 'datetime',
        'date_heure_reprise' => 'datetime',
        'derniere_sync_at' => 'datetime',
        'deconnecte_at' => 'datetime',
        'reconnecte_at' => 'datetime',
        'pause_at' => 'datetime',
        'alarme_fin_prevue' => 'datetime',
        'alarme_declenchee_at' => 'datetime',
        'alarme_active' => 'boolean',
        'duree_hors_ligne_secondes' => 'integer',
        'duree_pause_secondes' => 'integer',
        'alarme_duree_minutes' => 'integer',
        'alarme_montant' => 'integer',
        'alarme_historique' => 'array',
        'alarme_secondes_restantes' => 'integer',
    ];

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    public function poste(): BelongsTo { return $this->belongsTo(Poste::class); }
    public function recharges(): HasMany { return $this->hasMany(Recharge::class); }
    public function restitutions(): HasMany { return $this->hasMany(Restitution::class); }
    public function tarif(): BelongsTo { return $this->belongsTo(Tarif::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function appareilWifi(): BelongsTo { return $this->belongsTo(AppareilWifi::class, 'appareil_wifi_id'); }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function estConnecte(): bool
    {
        return $this->etat_wifi === self::WIFI_CONNECTE;
    }

    public function estDeconnecte(): bool
    {
        return $this->etat_wifi === self::WIFI_DECONNECTE;
    }

    public function estEnPause(): bool
    {
        return $this->etat_wifi === self::WIFI_EN_PAUSE;
    }

    public function estTermine(): bool
    {
        return $this->etat_wifi === self::WIFI_TERMINE;
    }

    /**
     * Vrai si le compteur doit tourner maintenant.
     */
    public function compteurActif(): bool
    {
        return $this->etat_wifi === self::WIFI_CONNECTE;
    }

    /**
     * Secondes consommées au moment présent.
     *
     * Décrémente du temps de :
     *   - duree_hors_ligne_secondes (déconnexions)
     *   - duree_pause_secondes (mises en pause manuelles)
     *   - du temps depuis pause_at si en pause actuellement
     */
    public function secondesConsommees(): int
    {
        if (!$this->date_heure_debut) return 0;

        // Terminé = figé
        if ($this->estTermine()) {
            return max(0, (int) $this->duree_consommee * 60);
        }

        // Fin virtuelle : maintenant, ou pause_at, ou deconnecte_at
        if ($this->estEnPause() && $this->pause_at) {
            $fin = $this->pause_at;
        } elseif ($this->estDeconnecte() && $this->deconnecte_at) {
            $fin = $this->deconnecte_at;
        } else {
            $fin = now();
        }

        $total = $this->date_heure_debut->diffInSeconds($fin);
        $total -= (int) $this->duree_hors_ligne_secondes;
        $total -= (int) $this->duree_pause_secondes;

        return max(0, $total);
    }
}