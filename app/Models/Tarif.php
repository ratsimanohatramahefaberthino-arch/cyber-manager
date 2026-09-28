<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarif extends Model
{
    protected $fillable = [
        'nom',
        'montant_par_minute',
        'montant_minimum',
        'actif',
        'personnalise',
        'description',
    ];

    protected $casts = [
        'montant_par_minute' => 'integer',
        'montant_minimum' => 'integer',
        'actif' => 'boolean',
        'personnalise' => 'boolean',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }
}