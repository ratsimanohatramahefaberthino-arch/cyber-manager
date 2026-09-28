<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('cyber_sessions', function (Blueprint $table) {
        $table->dateTime('date_heure_suspension')->nullable()->after('date_heure_fin_reelle');
        $table->dateTime('date_heure_reprise')->nullable()->after('date_heure_suspension');
    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('cyber_sessions', function (Blueprint $table) {
        $table->dropColumn([
            'date_heure_suspension',
            'date_heure_reprise',
        ]);
    });
}
};
