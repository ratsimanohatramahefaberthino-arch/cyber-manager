<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portail_parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique(); // ex: 'logo', 'titre', 'tarif_minute'
            $table->text('valeur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portail_parametres');
    }
};