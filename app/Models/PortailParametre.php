<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortailParametre extends Model
{
    protected $table = 'portail_parametres';
    protected $fillable = ['cle', 'valeur'];

    public static function get($cle, $defaut = null)
    {
        $param = self::where('cle', $cle)->first();
        return $param ? $param->valeur : $defaut;
    }

    public static function set($cle, $valeur)
    {
        self::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}