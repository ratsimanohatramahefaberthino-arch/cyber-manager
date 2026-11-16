<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Identifiant stable du serveur : créé une seule fois, conservé dans les sauvegardes de la base. */
    public function up(): void
    {
        if (!Schema::hasTable('cyber_settings')) {
            return;
        }
        if (DB::table('cyber_settings')->where('cle', 'serveur_id')->exists()) {
            return;
        }

        DB::table('cyber_settings')->insert(['cle' => 'serveur_id', 'valeur' => (string) Str::uuid()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cyber_settings')) {
            DB::table('cyber_settings')->where('cle', 'serveur_id')->delete();
        }
    }
};