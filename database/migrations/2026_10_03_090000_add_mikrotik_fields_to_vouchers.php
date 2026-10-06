<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Restreint l'identifiant à un serveur HotSpot précis côté MikroTik.
            // null/"all" = valable sur tous les serveurs.
            $table->string('serveur', 64)->nullable()->after('protege');

            // Profil HotSpot MikroTik. null = profil par défaut de l'app.
            $table->string('profil', 64)->nullable()->after('serveur');

            // Plafond de données en Mo appliqué par le MikroTik (limit-bytes-total).
            // null/0 = illimité.
            $table->unsignedInteger('limite_data_mo')->nullable()->after('profil');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['serveur', 'profil', 'limite_data_mo']);
        });
    }
};