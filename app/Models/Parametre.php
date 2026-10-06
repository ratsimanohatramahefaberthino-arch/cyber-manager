<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réglages clé/valeur simples (ex : mode du pool automatique). */
class Parametre extends Model
{
    protected $table = 'parametres';
    protected $primaryKey = 'cle';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['cle', 'valeur'];

    public static function get(string $cle, ?string $defaut = null): ?string
    {
        return static::query()->find($cle)?->valeur ?? $defaut;
    }

    public static function set(string $cle, string $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}