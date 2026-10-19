<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cyber_sessions', 'remarque')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->text('remarque')->nullable()->after('alarme_historique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cyber_sessions', 'remarque')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->dropColumn('remarque');
            });
        }
    }
};