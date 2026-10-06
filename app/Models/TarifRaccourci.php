<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifRaccourci extends Model
{
    protected $table = 'tarif_raccourcis';

    protected $fillable = ['tarif_id', 'montant', 'duree', 'libelle', 'ordre'];

    protected $casts = [
        'montant' => 'integer',
        'duree'   => 'integer',
        'ordre'   => 'integer',
    ];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    /** Durée formatée "1 h 15 min" + minutes entre parenthèses. */
    public function dureeLisible(): string
    {
        $m = (int) $this->duree;

        if ($m < 60) {
            return "{$m} min";
        }

        $h = intdiv($m, 60);
        $r = $m % 60;

        return $r === 0
            ? "{$h} h"
            : "{$h} h " . str_pad((string) $r, 2, '0', STR_PAD_LEFT) . " min";
    }
}