<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            // Identifiant unique (GUID) généré par l'agent à l'installation
            $table->string('identifiant_unique', 64)->nullable()->unique()->after('nom_windows');

            // Token d'authentification de l'agent (envoyé dans X-Agent-Token)
            $table->string('agent_token', 128)->nullable()->after('agent_url');

            // Heartbeat : dernière fois que l'agent a dit "je suis vivant"
            $table->timestamp('dernier_heartbeat')->nullable()->after('derniere_communication');

            // Version de l'agent (pour les mises à jour futures)
            $table->string('agent_version', 32)->nullable()->after('dernier_heartbeat');

            // Identifiants HotSpot MikroTik associés à ce poste
            $table->string('hotspot_username', 64)->nullable()->after('agent_version');
            $table->string('hotspot_password', 128)->nullable()->after('hotspot_username');

            // Nouveaux états unifiés : disponible, occupe, suspendu, verrouille, desactive, hors_ligne
            // On ne touche pas à l'ENUM existant car c'est un VARCHAR, on garde la flexibilité
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn([
                'identifiant_unique',
                'agent_token',
                'dernier_heartbeat',
                'agent_version',
                'hotspot_username',
                'hotspot_password',
            ]);
        });
    }
};