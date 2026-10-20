<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cyber_sessions', 'alarme_derniere_fin_at')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->timestamp('alarme_derniere_fin_at')->nullable()->after('alarme_fin_prevue');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cyber_sessions', 'alarme_derniere_fin_at')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->dropColumn('alarme_derniere_fin_at');
            });
        }
    }
};