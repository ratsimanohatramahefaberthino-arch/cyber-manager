<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appareils_wifi', function (Blueprint $table) {
            // Libellé choisi manuellement par le personnel, prioritaire sur host_name.
            $table->string('nom_affichage', 100)->nullable()->after('host_name');
        });
    }

    public function down(): void
    {
        Schema::table('appareils_wifi', function (Blueprint $table) {
            $table->dropColumn('nom_affichage');
        });
    }
};