<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            $table->string('nom')->after('id');
            $table->unsignedInteger('montant_par_minute')->default(20);
            $table->unsignedInteger('montant_minimum')->default(300);
            $table->boolean('actif')->default(true);
            $table->boolean('personnalise')->default(false);
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            $table->dropColumn([
                'nom',
                'montant_par_minute',
                'montant_minimum',
                'actif',
                'personnalise',
                'description',
            ]);
        });
    }
};