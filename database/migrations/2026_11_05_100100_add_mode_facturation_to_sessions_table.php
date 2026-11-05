<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sessions', 'mode_facturation')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            // forfait (durée limitée) | postpaye (compteur libre) | libre (débloqué, sans facturation)
            $table->string('mode_facturation', 16)->default('forfait');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sessions', 'mode_facturation')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('mode_facturation');
            });
        }
    }
};