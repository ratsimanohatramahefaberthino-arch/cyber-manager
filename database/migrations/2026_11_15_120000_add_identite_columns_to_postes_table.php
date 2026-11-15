<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            // Dernière adresse réseau vue par le serveur (pas celle que l'agent déclare)
            $table->string('derniere_ip_vue', 45)->nullable()->after('adresse_ip');

            // Dernier instant où deux ordinateurs ont semblé partager la même identité
            $table->timestamp('conflit_identite_at')->nullable()->after('derniere_ip_vue');
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['derniere_ip_vue', 'conflit_identite_at']);
        });
    }
};