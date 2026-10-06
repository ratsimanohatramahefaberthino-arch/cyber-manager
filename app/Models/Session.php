<?php

namespace App\Models;

use App\Models\Recharge;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tarif;
use App\Models\Voucher;

class Session extends Model
{
    protected $table = 'cyber_sessions';

    protected $fillable = [
        'poste_id',
        'type_session',
        'date_heure_debut',
        'date_heure_fin_prevue',
        'date_heure_fin_reelle',
        'date_heure_suspension',
        'date_heure_reprise',
        'duree_suspension',
        'duree_prevue',
        'duree_consommee',
        'temps_restant',
        'montant_initial',
        'montant_recharge',
        'montant_total',
        'montant_consomme',
        'montant_restant',
        'montant_a_reverser',
        'description',
        'etat',
        'motif_fin',
        'tarif_id',
        'voucher_id',
        'volume_entree',
        'volume_sortie',
        'volume_total',
        'mikrotik_active_id',
        'hotspot_username',
        'client_mac',
        'client_ip',
        'ssid',
        'appareil_wifi_id',
        'derniere_sync_at',
    ];

    protected $casts = [
        'date_heure_debut' => 'datetime',
        'date_heure_fin_prevue' => 'datetime',
        'date_heure_fin_reelle' => 'datetime',
        'date_heure_suspension' => 'datetime',
        'date_heure_reprise' => 'datetime',
        'duree_suspension' => 'integer',
        'derniere_sync_at' => 'datetime',
    ];

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function recharges()
    {
        return $this->hasMany(Recharge::class);
    }

    public function restitutions(): HasMany
    {
        return $this->hasMany(Restitution::class);
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function appareilWifi(): BelongsTo
    {
        return $this->belongsTo(AppareilWifi::class, 'appareil_wifi_id');
    }
}