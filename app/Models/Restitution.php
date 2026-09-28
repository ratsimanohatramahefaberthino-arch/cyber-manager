<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Restitution extends Model
{
    protected $fillable = [
        'session_id',
        'date_heure',
        'montant',
        'motif',
        'description',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'montant' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}