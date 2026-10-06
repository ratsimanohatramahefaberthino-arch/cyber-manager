<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->string('mikrotik_active_id')->nullable()->after('voucher_id');
            $table->string('hotspot_username')->nullable()->after('mikrotik_active_id');
            $table->string('client_mac')->nullable()->after('hotspot_username');
            $table->string('client_ip')->nullable()->after('client_mac');
            $table->timestamp('derniere_sync_at')->nullable()->after('client_ip');

            $table->index('mikrotik_active_id');
            $table->index('hotspot_username');
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropIndex(['mikrotik_active_id']);
            $table->dropIndex(['hotspot_username']);
            $table->dropColumn([
                'mikrotik_active_id',
                'hotspot_username',
                'client_mac',
                'client_ip',
                'derniere_sync_at',
            ]);
        });
    }
};