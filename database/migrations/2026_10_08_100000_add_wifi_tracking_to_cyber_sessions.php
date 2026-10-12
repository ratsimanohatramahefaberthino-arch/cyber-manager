<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            // État Wi-Fi spécifique (distinct du 'etat' général)
            $table->enum('etat_wifi', ['connecte', 'deconnecte', 'termine'])
                ->nullable()
                ->after('etat');

            // Traçage des déconnexions/reconnexions
            $table->timestamp('deconnecte_at')->nullable()->after('etat_wifi');
            $table->timestamp('reconnecte_at')->nullable()->after('deconnecte_at');
            $table->unsignedInteger('duree_hors_ligne_secondes')->default(0)->after('reconnecte_at');

            // Alarme
            $table->boolean('alarme_active')->default(false)->after('duree_hors_ligne_secondes');
            $table->enum('alarme_type', ['duree', 'montant'])->nullable()->after('alarme_active');
            $table->unsignedInteger('alarme_duree_minutes')->nullable()->after('alarme_type');
            $table->unsignedInteger('alarme_montant')->nullable()->after('alarme_duree_minutes');
            $table->timestamp('alarme_fin_prevue')->nullable()->after('alarme_montant');
            $table->timestamp('alarme_declenchee_at')->nullable()->after('alarme_fin_prevue');
            $table->string('alarme_message', 255)->nullable()->after('alarme_declenchee_at');
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'etat_wifi',
                'deconnecte_at',
                'reconnecte_at',
                'duree_hors_ligne_secondes',
                'alarme_active',
                'alarme_type',
                'alarme_duree_minutes',
                'alarme_montant',
                'alarme_fin_prevue',
                'alarme_declenchee_at',
                'alarme_message',
            ]);
        });
    }
};