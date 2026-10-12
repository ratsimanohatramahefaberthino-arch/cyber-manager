<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->json('alarme_historique')->nullable()->after('alarme_message');
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropColumn('alarme_historique');
        });
    }
};