<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cyber_settings')) {
            return;
        }

        $defauts = [
            'mikrotik_host'       => '128.0.1.1',
            'mikrotik_user'       => 'admin',
            'mikrotik_password'   => 'RootAdmin',
            'mikrotik_port'       => '8728',
            'portail_ip_serveur'  => null,
        ];

        foreach ($defauts as $cle => $valeur) {
            if (!DB::table('cyber_settings')->where('cle', $cle)->exists()) {
                DB::table('cyber_settings')->insert([
                    'cle'    => $cle,
                    'valeur' => $valeur,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cyber_settings')) {
            return;
        }
        DB::table('cyber_settings')->whereIn('cle', [
            'mikrotik_host', 'mikrotik_user', 'mikrotik_password', 'mikrotik_port', 'portail_ip_serveur'
        ])->delete();
    }
};