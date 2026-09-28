<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LotVoucher extends Model
{
    protected $fillable = [
        'nom',
        'quantite_prevue',
        'montant_unitaire',
        'duree_unitaire',
        'quantite_generee',
        'source',
        'description',
    ];

    protected $casts = [
        'quantite_prevue' => 'integer',
        'montant_unitaire' => 'integer',
        'duree_unitaire' => 'integer',
        'quantite_generee' => 'integer',
    ];

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }
}