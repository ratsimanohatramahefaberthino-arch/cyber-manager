<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recharge extends Model
{
    protected $fillable = [
        'session_id',
        'date_heure',
        'montant',
        'duree_ajoutee',
        'description',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'montant' => 'integer',
        'duree_ajoutee' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}