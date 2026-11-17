<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Efface les anciennes valeurs en dur, UNIQUEMENT si elles sont encore à leur valeur d'origine
     * (jamais modifiées par le gérant). Une configuration saisie par le gérant n'est pas touchée.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cyber_settings')) {
            return;
        }

        $val = fn (string $cle) => DB::table('cyber_settings')->where('cle', $cle)->value('valeur');

        if ($val('mikrotik_password') === 'RootAdmin' && $val('mikrotik_host') === '128.0.1.1') {
            DB::table('cyber_settings')
                ->whereIn('cle', ['mikrotik_host', 'mikrotik_user', 'mikrotik_password'])
                ->update(['valeur' => null, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // irréversible volontairement
    }
};