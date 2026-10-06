<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarif extends Model
{
    public const CIBLE_UNIFIE   = 'unifie';
    public const CIBLE_ETHERNET = 'ethernet';
    public const CIBLE_WIFI     = 'wifi';

    protected $fillable = [
        'cible',
        'montant_par_minute',
        'montant_minimum',
        'arrondi_actif',
        'unite_arrondi',
        'seuil_arrondi',
        'description',
    ];

    protected $casts = [
        'montant_par_minute' => 'integer',
        'montant_minimum'    => 'integer',
        'unite_arrondi'      => 'integer',
        'seuil_arrondi'      => 'integer',
        'arrondi_actif'      => 'boolean',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    public function raccourcis(): HasMany
    {
        return $this->hasMany(TarifRaccourci::class)
            ->orderBy('ordre')
            ->orderBy('montant');
    }

    // -----------------------------------------------------------------

    public function libelle(): string
    {
        return match ($this->cible) {
            self::CIBLE_UNIFIE   => 'Poste client + Wi-Fi',
            self::CIBLE_ETHERNET => 'Poste client',
            self::CIBLE_WIFI     => 'Wi-Fi',
            default              => ucfirst((string) $this->cible),
        };
    }

    public function couleur(): string
    {
        return match ($this->cible) {
            self::CIBLE_UNIFIE   => 'slate',
            self::CIBLE_ETHERNET => 'sky',
            self::CIBLE_WIFI     => 'violet',
            default              => 'slate',
        };
    }

    public function icone(): string
    {
        return match ($this->cible) {
            self::CIBLE_UNIFIE   => 'tag',
            self::CIBLE_ETHERNET => 'desktop',
            self::CIBLE_WIFI     => 'wifi',
            default              => 'tag',
        };
    }
}