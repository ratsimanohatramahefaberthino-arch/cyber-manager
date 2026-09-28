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
        Schema::create('postes', function (Blueprint $table) {
   		$table->id();
    		$table->string('nom_poste');
    		$table->string('nom_windows')->nullable();
    		$table->string('adresse_mac')->nullable();
    		$table->string('adresse_ip')->nullable();
    		$table->string('type_connexion')->default('ethernet');
    		$table->string('etat')->default('disponible');
    		$table->boolean('actif')->default(true);
    		$table->timestamp('derniere_communication')->nullable();
    		$table->timestamps();
	});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postes');
    }
};
