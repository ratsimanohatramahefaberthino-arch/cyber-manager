<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** États autorisés (RG57). */
    private const ETATS = [
        'disponible',
        'en_cours',
        'utilise',
        'expire',
        'desactive',
        'annule',
    ];

    public function up(): void
    {
        // Sécurité : la colonne etat est aujourd'hui un VARCHAR libre.
        // Si une valeur hors liste existe, la conversion en ENUM la
        // transformerait en '' (ou échouerait en mode strict) : on s'arrête.
        $inconnus = DB::table('vouchers')
            ->whereNotIn('etat', self::ETATS)
            ->distinct()
            ->pluck('etat');

        if ($inconnus->isNotEmpty()) {
            throw new RuntimeException(
                'Migration annulée : valeurs de vouchers.etat non prévues : '
                . $inconnus->implode(', ')
                . '. Corrigez-les avant de relancer.'
            );
        }

        // 1) Nouvelles colonnes + FK + index
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('type_voucher', ['temporaire', 'permanent'])
                ->default('temporaire')
                ->after('source');

            $table->foreignId('appareil_wifi_id')
                ->nullable()
                ->after('type_voucher')
                ->constrained('appareils_wifi')
                ->nullOnDelete();

            $table->index(['type_voucher', 'etat'], 'vouchers_type_etat_index');
        });

        // 2) VARCHAR → ENUM élargi (les lignes existantes sont conservées)
        $liste = "'" . implode("','", self::ETATS) . "'";
        DB::statement(
            "ALTER TABLE `vouchers` MODIFY `etat` ENUM({$liste}) NOT NULL DEFAULT 'disponible'"
        );
    }

    public function down(): void
    {
        // Retour au VARCHAR d'origine (avant de retirer les colonnes)
        DB::statement(
            "ALTER TABLE `vouchers` MODIFY `etat` VARCHAR(255) NOT NULL DEFAULT 'disponible'"
        );

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_type_etat_index');
            $table->dropForeign(['appareil_wifi_id']);
            $table->dropColumn(['appareil_wifi_id', 'type_voucher']);
        });
    }
};