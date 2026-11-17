<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aucune valeur par défaut : adresse, utilisateur et mot de passe du routeur
     * sont saisis par le gérant (Paramètres > Portail Captif > Réseau).
     */
    public function up(): void
    {
        // volontairement vide
    }

    public function down(): void
    {
        if (!Schema::hasTable('cyber_settings')) {
            return;
        }
        DB::table('cyber_settings')->whereIn('cle', [
            'mikrotik_host', 'mikrotik_user', 'mikrotik_password', 'mikrotik_port', 'portail_ip_serveur',
        ])->delete();
    }
};