<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_vouchers', function (Blueprint $table) {
            $table->id();

            $table->string('nom');

            $table->unsignedInteger('quantite_prevue');

            $table->unsignedInteger('montant_unitaire');

            $table->unsignedInteger('duree_unitaire');

            $table->unsignedInteger('quantite_generee')->default(0);

            $table->string('source')->default('cyber_manager');

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_vouchers');
    }
};