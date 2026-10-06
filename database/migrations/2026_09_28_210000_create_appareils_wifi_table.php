<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appareils_wifi', function (Blueprint $table) {
            $table->id();

            // Le unique() crée déjà l'index sur adresse_mac.
            // Format normalisé : AA:BB:CC:DD:EE:FF (17 caractères).
            $table->string('adresse_mac', 17)->unique();

            $table->string('adresse_ip', 45)->nullable(); // 45 = IPv6 max
            $table->string('host_name')->nullable();

            $table->enum('type_appareil', [
                'telephone',
                'ordinateur',
                'tablette',
                'inconnu',
            ])->nullable();

            $table->string('fabricant')->nullable();
            $table->string('modele')->nullable();
            $table->string('systeme')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('premiere_connexion')->nullable();
            $table->timestamp('derniere_connexion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appareils_wifi');
    }
};