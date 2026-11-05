<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosteMessage extends Model
{
    protected $table = 'poste_messages';

    public const SENS_ADMIN  = 'admin';
    public const SENS_CLIENT = 'client';

    protected $fillable = ['poste_id', 'sens', 'contenu', 'lu_at'];

    protected $casts = ['lu_at' => 'datetime'];

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}