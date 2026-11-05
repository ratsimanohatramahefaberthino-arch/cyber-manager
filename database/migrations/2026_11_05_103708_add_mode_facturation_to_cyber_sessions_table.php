<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cyber_sessions', 'mode_facturation')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->string('mode_facturation', 16)->default('forfait');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cyber_sessions', 'mode_facturation')) {
            Schema::table('cyber_sessions', function (Blueprint $table) {
                $table->dropColumn('mode_facturation');
            });
        }
    }
};