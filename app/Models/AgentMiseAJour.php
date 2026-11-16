<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMiseAJour extends Model
{
    protected $table = 'agent_mises_a_jour';

    protected $fillable = ['poste_id', 'version', 'succes', 'message'];

    protected $casts = ['succes' => 'boolean'];

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}