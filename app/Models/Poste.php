<?php

namespace App\Models;

use App\Models\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poste extends Model
{
    protected $fillable = [
        'nom_poste',
        'nom_windows',
        'adresse_mac',
        'adresse_ip',
        'type_connexion',
        'etat',
        'actif',
        'derniere_communication',
	'agent_url',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'derniere_communication' => 'datetime',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }
}