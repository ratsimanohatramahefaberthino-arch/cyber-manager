<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            // Stocke le nombre de secondes restantes de l'alarme lorsque l'appareil est déconnecté
            $table->unsignedInteger('alarme_secondes_restantes')->nullable()->after('alarme_fin_prevue');
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropColumn('alarme_secondes_restantes');
        });
    }
};