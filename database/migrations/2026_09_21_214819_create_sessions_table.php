<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
	Schema::create('cyber_sessions', function (Blueprint $table) {
    		$table->id();

    		$table->foreignId('poste_id')
       			->nullable()
        		->constrained('postes')
        		->nullOnDelete();

    		$table->string('type_session'); // ethernet ou wifi

    		$table->dateTime('date_heure_debut')->nullable();
    		$table->dateTime('date_heure_fin_prevue')->nullable();
    		$table->dateTime('date_heure_fin_reelle')->nullable();

    		$table->unsignedInteger('duree_prevue')->default(0);
    		$table->unsignedInteger('duree_consommee')->default(0);
    		$table->unsignedInteger('temps_restant')->default(0);

    		$table->unsignedInteger('montant_initial')->default(0);
    		$table->unsignedInteger('montant_recharge')->default(0);
    		$table->unsignedInteger('montant_total')->default(0);
    		$table->unsignedInteger('montant_consomme')->default(0);
    		$table->unsignedInteger('montant_restant')->default(0);
    		$table->unsignedInteger('montant_a_reverser')->default(0);

    		$table->text('description')->nullable();

    		$table->string('etat')->default('en_attente');
    		$table->string('motif_fin')->nullable();

    		$table->unsignedBigInteger('volume_entree')->default(0);
    		$table->unsignedBigInteger('volume_sortie')->default(0);
    		$table->unsignedBigInteger('volume_total')->default(0);

    		$table->timestamps();
	});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
