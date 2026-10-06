<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_raccourcis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tarif_id')
                ->constrained('tarifs')
                ->cascadeOnDelete();

            $table->unsignedInteger('montant');       // ex. 500
            $table->unsignedInteger('duree');         // minutes, ex. 25
            $table->string('libelle', 100)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->timestamps();

            $table->unique(['tarif_id', 'montant']);
            $table->index(['tarif_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_raccourcis');
    }
};