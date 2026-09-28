<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Voucher extends Model
{
    protected $fillable = [
        'lot_voucher_id',
        'username',
        'password',
        'code',
        'montant',
        'duree',
        'etat',
        'source',
        'mikrotik_id',
        'date_heure_creation',
        'date_heure_utilisation',
        'date_heure_expiration',
        'derniere_synchronisation',
        'description',
    ];

    protected $casts = [
        'montant' => 'integer',
        'duree' => 'integer',
        'date_heure_creation' => 'datetime',
        'date_heure_utilisation' => 'datetime',
        'date_heure_expiration' => 'datetime',
        'derniere_synchronisation' => 'datetime',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(
            LotVoucher::class,
            'lot_voucher_id'
        );
    }

    public function session(): HasOne
    {
        return $this->hasOne(
            Session::class,
            'voucher_id'
        );
    }
}