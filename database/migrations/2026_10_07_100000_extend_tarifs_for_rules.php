<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Retirer les anciennes colonnes, ajouter les nouvelles SANS unique
        Schema::table('tarifs', function (Blueprint $table) {
            $table->dropColumn(['nom', 'actif', 'personnalise', 'description']);

            $table->enum('cible', ['unifie', 'ethernet', 'wifi'])
                ->default('unifie')
                ->after('id');

            $table->boolean('arrondi_actif')->default(true)->after('montant_minimum');
            $table->unsignedInteger('unite_arrondi')->default(100)->after('arrondi_actif');
            $table->unsignedInteger('seuil_arrondi')->default(50)->after('unite_arrondi');
            $table->string('description', 500)->nullable()->after('seuil_arrondi');
        });

        // 2. Vider la table (delete plutôt que truncate pour ne pas casser les FK)
        DB::table('tarifs')->delete();

        // 3. Insérer UNE seule ligne unifiée par défaut
        DB::table('tarifs')->insert([
            'cible'              => 'unifie',
            'montant_par_minute' => 20,
            'montant_minimum'    => 300,
            'arrondi_actif'      => 1,
            'unite_arrondi'      => 100,
            'seuil_arrondi'      => 50,
            'description'        => 'Tarif initial du cybercafé (20 Ar/minute, minimum 300 Ar).',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // 4. Ajouter l'index UNIQUE maintenant qu'il n'y a qu'une ligne
        Schema::table('tarifs', function (Blueprint $table) {
            $table->unique('cible', 'tarifs_cible_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            $table->dropUnique('tarifs_cible_unique');
            $table->dropColumn([
                'cible',
                'arrondi_actif',
                'unite_arrondi',
                'seuil_arrondi',
                'description',
            ]);

            $table->string('nom')->nullable()->after('id');
            $table->boolean('actif')->default(true);
            $table->boolean('personnalise')->default(false);
            $table->text('description')->nullable();
        });
    }
};