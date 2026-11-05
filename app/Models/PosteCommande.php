<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosteCommande extends Model
{
    protected $table = 'poste_commandes';

    protected $fillable = [
        'poste_id', 'type', 'payload', 'statut', 'resultat',
        'recue_at', 'executee_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'recue_at' => 'datetime',
        'executee_at' => 'datetime',
    ];

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}