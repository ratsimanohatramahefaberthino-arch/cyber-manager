<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->timestamp('derniere_maj_at')->nullable();
            $table->string('derniere_maj_resultat', 16)->nullable();   // succes | echec
            $table->string('derniere_maj_message', 500)->nullable();
            $table->boolean('mode_maintenance')->default(false);        // appliqué par le Gardien à l'étape 5
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['derniere_maj_at', 'derniere_maj_resultat', 'derniere_maj_message', 'mode_maintenance']);
        });
    }
};