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
            $table->unsignedInteger('duree_suspension')
                ->default(0)
                ->after('date_heure_reprise');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropColumn('duree_suspension');
        });
    }
};