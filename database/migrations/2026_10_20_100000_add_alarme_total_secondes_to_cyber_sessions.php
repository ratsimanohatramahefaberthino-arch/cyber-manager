<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cyber_sessions', 'alarme_total_secondes')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                // Durée totale (en secondes) du cycle d'alarme en cours.
                // Sert à calculer la barre de progression : 1 - restant / total.
                $table->unsignedInteger('alarme_total_secondes')
                    ->nullable()
                    ->after('alarme_secondes_restantes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cyber_sessions', 'alarme_total_secondes')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->dropColumn('alarme_total_secondes');
            });
        }
    }
};