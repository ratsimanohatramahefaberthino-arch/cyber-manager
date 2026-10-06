<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->string('ssid', 64)->nullable()->after('client_ip');

            $table->foreignId('appareil_wifi_id')
                ->nullable()
                ->after('ssid')
                ->constrained('appareils_wifi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropForeign(['appareil_wifi_id']);
            $table->dropColumn(['appareil_wifi_id', 'ssid']);
        });
    }
};