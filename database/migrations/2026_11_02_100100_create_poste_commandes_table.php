<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poste_commandes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('poste_id')
                ->constrained('postes')
                ->cascadeOnDelete();

            // Type de commande : demarrer, fermer, suspendre, reprendre, verrouiller, debloquer, desactiver, arreter
            $table->string('type', 32);

            // Payload JSON (durée, montant, message, etc.)
            $table->json('payload')->nullable();

            // Statut : en_attente, recue, executee, echouee
            $table->enum('statut', ['en_attente', 'recue', 'executee', 'echouee'])
                ->default('en_attente');

            // Résultat renvoyé par l'agent
            $table->text('resultat')->nullable();

            $table->timestamp('recue_at')->nullable();
            $table->timestamp('executee_at')->nullable();

            $table->timestamps();

            $table->index(['poste_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poste_commandes');
    }
};