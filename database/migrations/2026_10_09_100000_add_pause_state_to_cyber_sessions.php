<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Étendre l'ENUM etat_wifi pour inclure "en_pause"
        DB::statement("
            ALTER TABLE cyber_sessions
            MODIFY COLUMN etat_wifi
            ENUM('connecte','deconnecte','termine','en_pause')
            NULL DEFAULT NULL
        ");

        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->timestamp('pause_at')->nullable()->after('reconnecte_at');
            $table->unsignedInteger('duree_pause_secondes')->default(0)->after('pause_at');
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropColumn(['pause_at', 'duree_pause_secondes']);
        });

        DB::statement("
            ALTER TABLE cyber_sessions
            MODIFY COLUMN etat_wifi
            ENUM('connecte','deconnecte','termine')
            NULL DEFAULT NULL
        ");
    }
};