<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->foreignId('tarif_id')
                ->nullable()
                ->after('poste_id')
                ->constrained('tarifs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cyber_sessions', function (Blueprint $table) {
            $table->dropForeign(['tarif_id']);
            $table->dropColumn('tarif_id');
        });
    }
};