<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cyber_settings')) return;

        if (!DB::table('cyber_settings')->where('cle', 'portail_domaine')->exists()) {
            DB::table('cyber_settings')->insert([
                'cle' => 'portail_domaine',
                'valeur' => 'portail.izara', // Valeur par défaut, modifiable via l'interface
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cyber_settings')) {
            DB::table('cyber_settings')->where('cle', 'portail_domaine')->delete();
        }
    }
};